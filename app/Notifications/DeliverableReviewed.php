<?php

namespace App\Notifications;

use App\Mail\ReminderMail;
use App\Models\Deliverable;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu PIC project bahwa klien sudah menilai hasil pekerjaan.
 *
 * Sebelumnya penilaian klien hanya menjadi baris log aktivitas yang tak
 * seorang pun mengawasinya, jadi revisi bisa menganggur berhari-hari.
 */
class DeliverableReviewed extends Notification
{
    public function __construct(
        private Deliverable $deliverable,
        private bool $approved,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): ReminderMail
    {
        $project = $this->deliverable->project;
        $verdict = $this->approved ? 'menyetujui' : 'meminta revisi atas';

        return (new ReminderMail(
            title: ($this->approved ? 'Disetujui klien' : 'Klien meminta revisi').' — '.$project->title,
            heading: 'Klien '.$verdict.' hasil pekerjaan',
            lines: array_filter([
                'Project: '.$project->title.' ('.$project->client->name.')',
                'Hasil: '.$this->deliverable->title.' versi '.$this->deliverable->version,
                $this->deliverable->review_note ? 'Catatan klien: '.$this->deliverable->review_note : null,
            ]),
            url: route('projects.show', $project),
            urlLabel: 'Buka project',
        ))->to($notifiable->email);
    }
}
