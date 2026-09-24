<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A message from the public Contact page, sent to our support inbox.
 * Plain text only, and "Reply" in the inbox goes straight to the sender.
 */
class ContactMessage extends Mailable
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $topic,
        public string $messageText,
        public ?int $userId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            // $topic is one of the fixed choices from ContactController —
            // never free text — so nothing user-typed reaches the subject.
            subject: "[Contact] {$this->topic}",
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.contact');
    }
}
