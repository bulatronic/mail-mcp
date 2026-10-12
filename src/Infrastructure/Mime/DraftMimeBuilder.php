<?php

declare(strict_types=1);

namespace MailMcp\Infrastructure\Mime;

use MailMcp\Domain\Draft\Draft;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\UnstructuredHeader;

final class DraftMimeBuilder
{
    public function build(Draft $draft): string
    {
        $from = $draft->fromName !== null && trim($draft->fromName) !== ''
            ? new Address($draft->fromEmail, $draft->fromName)
            : new Address($draft->fromEmail);

        $to = trim($draft->to->name) !== ''
            ? new Address($draft->to->email, $draft->to->name)
            : new Address($draft->to->email);

        $email = new Email()
            ->from($from)
            ->to($to)
            ->subject($draft->subject)
            ->text($draft->bodyText, 'utf-8');

        $headers = $email->getHeaders();
        $headers->addIdHeader('In-Reply-To', trim($draft->inReplyTo, " \t<>"));
        $headers->addTextHeader('References', $draft->references);
        $headers->add(new UnstructuredHeader('X-Drafted-By', 'mail-mcp'));

        return $email->toString();
    }
}
