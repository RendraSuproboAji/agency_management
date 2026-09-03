<?php

namespace App\Notifications;

use App\Mail\ReminderMail;
use Illuminate\Notifications\Notification;

/**
 * Tautan setel ulang kata sandi.
 *
 * Memakai kembali ReminderMail — bentuknya memang sudah "judul, beberapa baris,
 * satu tautan", persis yang dibutuhkan di sini.
 */
class ResetPassword extends Notification
{
    public function __construct(private string $token, private string $guard) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): ReminderMail
    {
        $route = $this->guard === 'client' ? 'portal.password.reset' : 'password.reset';

        $url = route($route, ['token' => $this->token]).'?email='.urlencode($notifiable->email);

        return (new ReminderMail(
            title: 'Setel ulang kata sandi',
            heading: 'Permintaan setel ulang kata sandi',
            lines: [
                'Kami menerima permintaan menyetel ulang kata sandi untuk email ini.',
                'Tautan di bawah berlaku 60 menit.',
                'Kalau bukan Anda yang meminta, abaikan saja email ini — kata sandinya tidak berubah.',
            ],
            url: $url,
            urlLabel: 'Setel ulang kata sandi',
        ))->to($notifiable->email);
    }
}
