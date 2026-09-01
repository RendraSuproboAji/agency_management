<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PIC diberi tahu begitu klien menulis dari portal.
 *
 * Tanpa ini pesannya hanya menjadi baris catatan yang tak seorang pun
 * mengawasinya — persoalan yang sama seperti penilaian deliverable dulu.
 */
class ClientMessagePosted extends Notification
{
    use Queueable;

    public function __construct(private Note $note) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->note->project;

        return (new MailMessage)
            ->subject('Pesan baru dari klien — '.$project->title)
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->note->authorName().' menulis di project "'.$project->title.'":')
            ->line('"'.$this->note->body.'"')
            ->action('Buka project', route('projects.show', $project))
            ->line('Balas lewat catatan project dan tandai dibagikan agar klien ikut membacanya.');
    }
}
