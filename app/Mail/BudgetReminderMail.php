<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BudgetReminderMail extends Mailable
{
    public function __construct(public string $heading, public string $bodyText, public string $actionUrl, public string $senderAddress, public string $senderName) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->senderAddress, $this->senderName), subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.budget-reminder');
    }
}
