<?php

namespace App\Console\Commands;

use App\BudgetNotificationMail;
use App\BudgetNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('moola:notify')]
#[Description('Generate due-date reminders and budget limit notifications for eligible members')]
class SendBudgetNotifications extends Command
{
    public function handle(BudgetNotifications $notifications, BudgetNotificationMail $mail): int
    {
        $notifications->scan();
        $mail->deliver();
        $this->info('Budget notifications checked.');

        return self::SUCCESS;
    }
}
