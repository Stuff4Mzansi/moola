<?php

namespace App\Observers;

use App\Models\SavingsContribution;
use App\SavingsAccounts;

class SavingsContributionObserver
{
    public function saved(SavingsContribution $entry): void
    {
        app(SavingsAccounts::class)->changed($entry, $entry->getRawOriginal('id') === null ? null : $entry->getRawOriginal());
    }

    public function deleted(SavingsContribution $entry): void
    {
        if (! $entry->isForceDeleting()) {
            $previous = $entry->getAttributes();
            $previous['deleted_at'] = null;
            app(SavingsAccounts::class)->changed($entry, $previous);
        }
    }
}
