<?php

declare(strict_types=1);

namespace MailMcp\Ports;

use MailMcp\Domain\Draft\Draft;
use MailMcp\Domain\Draft\SavedDraftInfo;

interface DraftWriter
{
    /**
     * APPEND a draft into Drafts with \Draft and \Seen flags.
     */
    public function appendDraft(Draft $draft): SavedDraftInfo;
}
