<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

use Dom\Comment;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Converts untrusted HTML email bodies to plain text.
 *
 * CSS visibility checks are heuristic: only the inline style attribute is
 * inspected (not stylesheets, classes, or computed style).
 */
final class HtmlToText
{
    /** @var list<string> */
    private const array REMOVE_TAGS = ['script', 'style', 'head', 'template', 'noscript'];

    /** @var list<string> */
    private const array BLOCK_TAGS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl', 'dt',
        'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main',
        'nav', 'ol', 'p', 'pre', 'section', 'table', 'tr', 'ul',
    ];

    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $this->stripHiddenAndUnsafe($document);

        $root = $document->body ?? $document->documentElement;
        $text = $this->extractText($root);

        return TextSanitizer::sanitize($text);
    }

    private function stripHiddenAndUnsafe(HTMLDocument $document): void
    {
        /** @var list<Node> $toRemove */
        $toRemove = [];

        $this->collectComments($document, $toRemove);

        foreach (self::REMOVE_TAGS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag), false) as $element) {
                $toRemove[] = $element;
            }
        }

        foreach (iterator_to_array($document->getElementsByTagName('*'), false) as $element) {
            if ($this->shouldRemoveElement($element)) {
                $toRemove[] = $element;
            }
        }

        foreach ($toRemove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @param list<Node> $toRemove
     */
    private function collectComments(Node $node, array &$toRemove): void
    {
        if ($node instanceof Comment) {
            $toRemove[] = $node;

            return;
        }

        if (!$node->hasChildNodes()) {
            return;
        }

        foreach (iterator_to_array($node->childNodes, false) as $child) {
            $this->collectComments($child, $toRemove);
        }
    }

    private function shouldRemoveElement(Element $element): bool
    {
        if ($element->hasAttribute('hidden')) {
            return true;
        }

        $style = $element->getAttribute('style') ?? '';
        if ($style !== '' && $this->isHiddenByInlineStyle($style)) {
            return true;
        }

        return $this->isOneByOneImage($element);
    }

    /**
     * Heuristic: only the inline style attribute is inspected.
     */
    private function isHiddenByInlineStyle(string $style): bool
    {
        $style = strtolower($style);

        if (preg_match('/display\s*:\s*none\b/', $style) === 1) {
            return true;
        }
        if (preg_match('/visibility\s*:\s*hidden\b/', $style) === 1) {
            return true;
        }
        if (preg_match('/opacity\s*:\s*0(?:\.0+)?\s*(?:;|$)/', $style) === 1) {
            return true;
        }
        if (preg_match('/font-size\s*:\s*0(?:px|em|rem|%)?\b/', $style) === 1) {
            return true;
        }
        if (preg_match('/max-height\s*:\s*0(?:px|em|rem|%)?\b/', $style) === 1) {
            return true;
        }

        return false;
    }

    private function isOneByOneImage(Element $element): bool
    {
        if (strtolower($element->tagName) !== 'img') {
            return false;
        }

        $width = trim($element->getAttribute('width') ?? '');
        $height = trim($element->getAttribute('height') ?? '');
        if ($width === '1' && $height === '1') {
            return true;
        }

        $style = strtolower($element->getAttribute('style') ?? '');
        if ($style === '') {
            return false;
        }

        $widthOne = preg_match('/(?:^|;)\s*width\s*:\s*1(?:px)?\s*(?:;|$)/', $style) === 1;
        $heightOne = preg_match('/(?:^|;)\s*height\s*:\s*1(?:px)?\s*(?:;|$)/', $style) === 1;

        return $widthOne && $heightOne;
    }

    private function extractText(?Node $node): string
    {
        if ($node === null) {
            return '';
        }

        if ($node instanceof Text) {
            return $node->data;
        }

        if (!$node instanceof Element) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'br') {
            return "\n";
        }

        if ($tag === 'a') {
            return $this->formatLink($node);
        }

        $parts = [];
        foreach ($node->childNodes as $child) {
            $parts[] = $this->extractText($child);
        }
        $inner = implode('', $parts);

        if (in_array($tag, self::BLOCK_TAGS, true)) {
            return rtrim($inner, "\n") . "\n";
        }

        return $inner;
    }

    private function formatLink(Element $anchor): string
    {
        $parts = [];
        foreach ($anchor->childNodes as $child) {
            $parts[] = $this->extractText($child);
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');
        $href = trim($anchor->getAttribute('href') ?? '');

        if ($href !== '' && preg_match('/^\s*javascript:/i', $href) === 1) {
            $href = '';
        }

        // Resources are never fetched — href is used as a literal string only.
        if ($href === '') {
            return $text;
        }

        if ($text === '' || strcasecmp($text, $href) === 0) {
            return $text !== '' ? $text : $href;
        }

        return sprintf('%s (%s)', $text, $href);
    }
}
