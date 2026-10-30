<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Every email the system sends uses this one layout: the company's logo or name on a colour band, a short message,
 * an optional table of facts, one button, and (for optional emails only) an unsubscribe link.
 */
class BrandedMail extends Mailable
{
    /**
     * @param  list<string>  $paragraphs
     * @param  array<string, string>  $facts  label => value
     */
    public function __construct(
        public string $mailSubject,
        public string $heading,
        public array $paragraphs = [],
        public array $facts = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $greeting = null,
        public ?string $unsubscribeUrl = null,
        public ?string $htmlBody = null,   // rich text from the editor; cleaned again when the email is built
    ) {}

    public function envelope(): Envelope
    {
        $reply = trim((string) Setting::get('site_contact_email', ''));

        return new Envelope(
            subject: $this->mailSubject,
            replyTo: filter_var($reply, FILTER_VALIDATE_EMAIL) ? [$reply] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.branded');
    }
}
