<?php

namespace App\Notifications;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** PIC diberi tahu begitu klien menyetujui penawaran dari portal. */
class QuotationAccepted extends Notification
{
    use Queueable;

    public function __construct(private Quotation $quotation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->quotation->project;

        return (new MailMessage)
            ->subject('Penawaran '.$this->quotation->number.' disetujui klien')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->quotation->accepted_by.' menyetujui penawaran '.$this->quotation->number
                .' untuk project "'.$project->title.'".')
            ->action('Buka penawaran', route('quotations.show', [$project, $this->quotation]))
            ->line('Langkah berikutnya: terbitkan invoice dan jadwalkan sesi capture.');
    }
}
