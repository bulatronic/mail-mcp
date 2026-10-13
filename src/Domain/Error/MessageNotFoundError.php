<?php

declare(strict_types=1);

namespace MailMcp\Domain\Error;

/**
 * Thrown only when a message UID does not exist in the target mailbox.
 */
final class MessageNotFoundError extends MailboxError {}
