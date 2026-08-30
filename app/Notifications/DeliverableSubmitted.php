<?php

namespace App\Notifications;

use App\Mail\ReminderMail;
use App\Models\Deliverable;
use Illuminate\Notifications\Notification;

/**
 * Memberi tahu klien bahwa ada hasil pekerjaan baru menunggu ditinjau.
 *
 * Tanpa ini klien hanya tahu kalau kebetulan membuka portal, dan seluruh
 * siklus review bergantung pada kebetulan itu.
 */
class DeliverableSubmitted extends Notification
{
    public function __construct(private Deliverable $deliverable) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): ReminderMail
    {
        $project = $this->deliverable->project;

        return (new ReminderMail(
            title: 'Hasil pekerjaan siap ditinjau — '.$project->title,
            heading: 'Ada hasil pekerjaan yang menunggu tinjauan Anda',
            lines: array_filter([
                'Project: '.$project->title,
                'Hasil: '.$this->deliverable->title.' versi '.$this->deliverable->version,
                $this->deliverable->scene?->name ? 'Scene: '.$this->deliverable->scene->name : null,
                'Silakan buka portal untuk melihatnya, lalu setujui atau minta revisi.',
            ]),
            url: route('portal.projects.show', $project),
            urlLabel: 'Buka portal',
        ))->to($notifiable->email);
    }
}
