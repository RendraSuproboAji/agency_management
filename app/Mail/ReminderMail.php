<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Satu bentuk email untuk ketiga pengingat: isinya hanya judul, beberapa
 * baris, dan satu tautan. Memisahkannya jadi tiga Mailable hanya akan
 * menggandakan template yang sama.
 */
class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines */
    public function __construct(
        // Bukan $subject: Mailable sudah punya properti itu tanpa tipe, dan
        // mendeklarasikannya ulang dengan tipe adalah fatal error.
        public string $title,
        public string $heading,
        public array $lines,
        public ?string $url = null,
        public ?string $urlLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder');
    }
}
