<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

final class HtmlToText
{
    public function convert(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;
        $html = preg_replace('#<(img)\b[^>]*(?:display\s*:\s*none|width\s*=\s*["\']?1["\']?|height\s*=\s*["\']?1["\']?)[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#<(div|span|p|td|tr|table|section|font)\b[^>]*\b(?:display\s*:\s*none|visibility\s*:\s*hidden)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</p>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#</div>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</tr>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</li>#i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return TextSanitizer::sanitize($text);
    }
}
