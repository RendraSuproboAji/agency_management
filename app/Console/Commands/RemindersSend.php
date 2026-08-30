<?php

namespace App\Console\Commands;

use App\Mail\ReminderMail;
use App\Models\CaptureSession;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ReminderLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Pengingat harian: sesi besok, deadline project tiga hari lagi, dan invoice
 * yang jatuh tempo hari ini.
 *
 * Dikirim langsung tanpa queue. Volumenya beberapa email per hari, jadi
 * menambah container worker hanya untuk ini tidak sepadan; kalau SMTP mati,
 * perintahnya gagal dan bisa dijalankan ulang tanpa menggandakan email yang
 * sudah berhasil — lihat send() untuk cara log dan pengiriman disinkronkan.
 */
class RemindersSend extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Kirim pengingat sesi, deadline project, dan invoice jatuh tempo';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        $sent += $this->sessionReminders($today);
        $sent += $this->deadlineReminders($today);
        $sent += $this->invoiceReminders($today);

        $this->info($sent.' pengingat terkirim.');

        return self::SUCCESS;
    }

    private function sessionReminders(Carbon $today): int
    {
        $sessions = CaptureSession::query()
            ->with(['project.client', 'crew'])
            ->whereHas('project')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [
                $today->copy()->addDay()->startOfDay(),
                $today->copy()->addDay()->endOfDay(),
            ])
            ->get();

        $count = 0;

        foreach ($sessions as $session) {
            $to = $session->crew?->email ?? $session->project->owner?->email;

            $count += $this->send('session', $session, $today, $to, new ReminderMail(
                title: 'Pengingat sesi besok — '.$session->project->title,
                heading: 'Sesi pengambilan gambar besok',
                lines: array_filter([
                    'Project: '.$session->project->title.' ('.$session->project->client->name.')',
                    'Jadwal: '.$session->scheduled_at->format('d M Y H:i'),
                    $session->location ? 'Lokasi: '.$session->location : null,
                ]),
                url: route('projects.show', $session->project),
                urlLabel: 'Buka project',
            ));
        }

        return $count;
    }

    private function deadlineReminders(Carbon $today): int
    {
        $projects = Project::query()
            ->with('client', 'owner')
            ->whereNotIn('status', ['delivered', 'archived'])
            ->whereDate('deadline', $today->copy()->addDays(3))
            ->get();

        $count = 0;

        foreach ($projects as $project) {
            $count += $this->send('deadline', $project, $today, $project->owner?->email, new ReminderMail(
                title: 'Deadline tiga hari lagi — '.$project->title,
                heading: 'Deadline project tinggal tiga hari',
                lines: [
                    'Project: '.$project->title.' ('.$project->client->name.')',
                    'Deadline: '.$project->deadline->format('d M Y'),
                    'Status saat ini: '.$project->status,
                ],
                url: route('projects.show', $project),
                urlLabel: 'Buka project',
            ));
        }

        return $count;
    }

    private function invoiceReminders(Carbon $today): int
    {
        $invoices = Invoice::query()
            ->with('project.client', 'payments')
            ->whereNotIn('status', ['draft', 'paid', 'void'])
            ->whereDate('due_at', $today)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->outstanding() > 0);

        $count = 0;

        foreach ($invoices as $invoice) {
            $count += $this->send('invoice', $invoice, $today, $invoice->project->client->email, new ReminderMail(
                title: 'Invoice '.$invoice->number.' jatuh tempo hari ini',
                heading: 'Invoice jatuh tempo hari ini',
                lines: [
                    'Nomor: '.$invoice->number,
                    'Project: '.$invoice->project->title,
                    'Sisa tagihan: Rp '.number_format($invoice->outstanding(), 0, ',', '.'),
                ],
                url: route('portal.projects.show', $invoice->project),
                urlLabel: 'Buka portal',
            ));
        }

        return $count;
    }

    /**
     * Kirim satu pengingat bila belum pernah dikirim hari ini.
     *
     * Baris log ditulis lebih dulu supaya unique index menengahi dua proses
     * yang berjalan bersamaan — yang kedua kalah di index dan berhenti tanpa
     * mengirim. Bila pengirimannya sendiri gagal, barisnya dihapus lagi
     * sehingga menjalankan ulang perintahnya benar-benar mencoba lagi.
     */
    private function send(string $type, Model $target, Carbon $today, ?string $to, ReminderMail $mail): int
    {
        if (! $to) {
            return 0;
        }

        try {
            $log = ReminderLog::create([
                'type' => $type,
                'remindable_type' => $target->getMorphClass(),
                'remindable_id' => $target->getKey(),
                'sent_on' => $today,
            ]);
        } catch (UniqueConstraintViolationException) {
            return 0;
        }

        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $exception) {
            $log->delete();

            throw $exception;
        }

        return 1;
    }
}
