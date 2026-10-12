<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

use DirectoryTree\ImapEngine\Address;
use DirectoryTree\ImapEngine\BodyStructurePart;
use DirectoryTree\ImapEngine\FileMessage;
use DirectoryTree\ImapEngine\Message;
use DirectoryTree\ImapEngine\MessageInterface;
use MailMcp\Domain\Message\AttachmentMeta;
use MailMcp\Domain\Message\MessageContent;
use MailMcp\Domain\Message\MessageSummary;

final readonly class MessageContentFactory
{
    public function __construct(
        private HtmlToText               $htmlToText = new HtmlToText(),
        private AutomatedMessageDetector $automatedDetector = new AutomatedMessageDetector(),
    ) {
    }

    public function summaryFromMessage(MessageInterface $message, int $uid, ?int $size = null, ?bool $hasAttachments = null): MessageSummary
    {
        $from = $message->from();
        $to = $this->formatAddresses(array_values($message->to()));
        $date = $message->date();
        $inReplyTo = $message->inReplyTo();

        return new MessageSummary(
            uid: $uid,
            fromName: $from?->name() ?? '',
            fromEmail: $from?->email() ?? '',
            to: $to,
            subject: TextSanitizer::sanitize((string) ($message->subject() ?? '')),
            date: $date?->toIso8601String() ?? '',
            messageId: (string) ($message->messageId() ?? ''),
            inReplyTo: $inReplyTo[0] ?? '',
            size: $size ?? $message->size() ?? 0,
            hasAttachments: $hasAttachments ?? $this->detectHasAttachments($message),
            isAutomated: $this->isAutomatedFromMessage($message, $from),
        );
    }

    public function contentFromMessage(MessageInterface $message, int $uid, int $maxChars): MessageContent
    {
        if ($message instanceof Message) {
            $message->bodyStructure(fetch: true);
        }

        [$text, $truncated] = TextSanitizer::truncate(
            $this->extractText($message),
            $maxChars,
        );

        return new MessageContent(
            uid: $uid,
            from: $this->formatAddress($message->from()),
            to: $this->formatAddresses(array_values($message->to())),
            cc: $this->formatAddresses(array_values($message->cc())),
            subject: TextSanitizer::sanitize((string) ($message->subject() ?? '')),
            date: $message->date()?->toIso8601String() ?? '',
            text: $text,
            truncated: $truncated,
            attachments: $this->attachmentMeta($message),
        );
    }

    public function contentFromRawEml(string $rawEml, int $uid, int $maxChars): MessageContent
    {
        return $this->contentFromMessage(new FileMessage($rawEml), $uid, $maxChars);
    }

    public function summaryFromRawEml(string $rawEml, int $uid): MessageSummary
    {
        return $this->summaryFromMessage(new FileMessage($rawEml), $uid);
    }

    private function extractText(MessageInterface $message): string
    {
        if ($message instanceof Message) {
            $plain = $message->text(fetch: true);
            if (is_string($plain) && trim($plain) !== '') {
                return TextSanitizer::sanitize($plain);
            }

            $html = $message->html(fetch: true);
            if (is_string($html) && trim($html) !== '') {
                return $this->htmlToText->convert($html);
            }

            return '';
        }

        $plain = $message->text();
        if (is_string($plain) && trim($plain) !== '') {
            return TextSanitizer::sanitize($plain);
        }

        $html = $message->html();
        if (is_string($html) && trim($html) !== '') {
            return $this->htmlToText->convert($html);
        }

        return '';
    }

    /**
     * @return list<AttachmentMeta>
     */
    private function attachmentMeta(MessageInterface $message): array
    {
        if ($message instanceof Message) {
            return array_values(array_map(
                static fn (BodyStructurePart $part): AttachmentMeta => new AttachmentMeta(
                    filename: $part->filename() ?? 'unnamed',
                    mime: $part->contentType(),
                    size: $part->size() ?? 0,
                ),
                $message->bodyStructure(fetch: true)?->attachments() ?? [],
            ));
        }

        $items = [];
        foreach ($message->attachments() as $attachment) {
            $stream = $attachment->contentStream();
            $size = $stream->getSize();
            if ($size === null) {
                $size = strlen($attachment->contents());
            }

            $items[] = new AttachmentMeta(
                filename: $attachment->filename() ?? 'unnamed',
                mime: $attachment->contentType(),
                size: $size,
            );
        }

        return $items;
    }

    private function detectHasAttachments(MessageInterface $message): bool
    {
        if ($message instanceof Message && $message->hasBodyStructure()) {
            return $message->bodyStructure()?->hasAttachments() ?? false;
        }

        return $message->hasAttachments();
    }

    private function isAutomatedFromMessage(MessageInterface $message, ?Address $from): bool
    {
        return $this->automatedDetector->isAutomated(
            $from?->email(),
            $message->header('List-Unsubscribe')?->getValue(),
            $message->header('Precedence')?->getValue(),
            $message->header('Auto-Submitted')?->getValue(),
        );
    }

    /**
     * @param list<Address> $addresses
     */
    private function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map($this->formatAddress(...), $addresses));
    }

    private function formatAddress(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $name = trim($address->name());
        $email = trim($address->email());
        if ($name === '') {
            return $email;
        }

        return sprintf('%s <%s>', $name, $email);
    }
}
