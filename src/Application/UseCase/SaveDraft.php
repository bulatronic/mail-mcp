<?php

declare(strict_types=1);

namespace MailMcp\Application\UseCase;

use MailMcp\Application\Draft\ReplyQuote;
use MailMcp\Application\Draft\ReplySubject;
use MailMcp\Domain\Draft\Draft;
use MailMcp\Domain\Draft\SaveDraftResult;
use MailMcp\Domain\Error\DraftError;
use MailMcp\Domain\Error\MessageNotFoundError;
use MailMcp\Domain\Message\ReplySource;
use MailMcp\Ports\DraftWriter;
use MailMcp\Ports\MailboxReader;

final class SaveDraft
{
    private const int MIN_BODY = 1;
    private const int MAX_BODY = 10000;

    public function __construct(
        private readonly MailboxReader $mailboxReader,
        private readonly DraftWriter $draftWriter,
        private readonly string $fromEmail,
        private readonly ?string $fromName,
    ) {}

    public function execute(int $replyToUid, string $body): SaveDraftResult
    {
        $body = $this->assertBody($body);

        if ($replyToUid < 1) {
            throw new DraftError('reply_to_uid must be >= 1');
        }

        try {
            $source = $this->mailboxReader->getReplySource($replyToUid);
        } catch (MessageNotFoundError $e) {
            throw new DraftError(sprintf('Source message UID %d not found', $replyToUid), 0, $e);
        }

        if ($source->messageId === '') {
            throw new DraftError('Source message has no Message-ID');
        }

        if (!$this->isValidEmail($source->recipient->email)) {
            throw new DraftError('Recipient address is invalid');
        }

        $replyToDiffers = $this->replyToDiffersFromSender($source);

        $existing = $this->mailboxReader->findDraftByInReplyTo($source->messageId);
        if ($existing !== null) {
            return SaveDraftResult::fromExisting($existing, $replyToDiffers);
        }

        $messageId = $this->bracketId($source->messageId);
        $references = trim($source->references);
        $references = $references === ''
            ? $messageId
            : $references . ' ' . $messageId;

        $subject = ReplySubject::fromOriginal($source->subject);
        $draft = new Draft(
            to: $source->recipient,
            fromEmail: $this->fromEmail,
            fromName: $this->fromName,
            subject: $subject,
            inReplyTo: $messageId,
            references: $references,
            bodyText: ReplyQuote::append($body, $source),
        );

        return SaveDraftResult::created($this->draftWriter->appendDraft($draft), $replyToDiffers);
    }

    private function replyToDiffersFromSender(ReplySource $source): bool
    {
        $replyTo = trim((string) $source->replyToEmail);
        if ($replyTo === '') {
            return false;
        }

        $replyDomain = $this->emailDomain($replyTo);
        $fromDomain = $this->emailDomain($source->fromEmail);
        if ($replyDomain === '' || $fromDomain === '') {
            return false;
        }

        return strcasecmp($replyDomain, $fromDomain) !== 0;
    }

    private function emailDomain(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return '';
        }

        return substr($email, $at + 1);
    }

    private function assertBody(string $body): string
    {
        $length = mb_strlen($body);
        if ($length < self::MIN_BODY || $length > self::MAX_BODY) {
            throw new DraftError(sprintf(
                'body length must be between %d and %d characters',
                self::MIN_BODY,
                self::MAX_BODY,
            ));
        }

        return $body;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function bracketId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return $id;
        }

        if ($id[0] !== '<') {
            $id = '<' . $id;
        }
        if (!str_ends_with($id, '>')) {
            $id .= '>';
        }

        return $id;
    }
}
