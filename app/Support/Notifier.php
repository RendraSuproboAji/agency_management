<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim notifikasi tanpa mempertaruhkan aksi yang memicunya.
 *
 * Ini sengaja berbeda dari `reminders:send`, yang justru harus gagal keras
 * karena ia perintah latar yang akan dijalankan ulang. Di sini pemicunya adalah
 * permintaan web: SMTP yang mati tidak boleh membuat staf kehilangan
 * deliverable yang baru diserahkan atau membatalkan persetujuan klien.
 */
class Notifier
{
    public static function send(mixed $notifiable, Notification $notification): bool
    {
        if (! self::canReceive($notifiable)) {
            return false;
        }

        try {
            $notifiable->notify($notification);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Notifikasi gagal dikirim.', [
                'notification' => $notification::class,
                'recipient' => $notifiable->email,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Klien yang portalnya belum aktif tidak dikirimi apa pun: setiap
     * notifikasi menautkan ke portal, dan mengirim tautan yang tidak bisa
     * dibuka lebih buruk daripada tidak mengirim sama sekali.
     */
    private static function canReceive(mixed $notifiable): bool
    {
        if (! $notifiable || blank($notifiable->email)) {
            return false;
        }

        return $notifiable instanceof Client ? $notifiable->canUsePortal() : true;
    }
}
