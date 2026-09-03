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

    /**
     * Pengingat jatuh tempo, lalu susulan pada H+1, H+7, dan H+14.
     *
     * Tahapnya dipilih dengan ambang ("sudah lewat sekian hari"), bukan
     * pencocokan tanggal persis. Dengan tanggal persis, scheduler yang absen
     * sehari saja membuat pengingat hari itu hilang tanpa jejak; dengan ambang,
     * tahap yang terlewat tetap terkirim pada jalan berikutnya.
     */
    private const OVERDUE_STAGES = [14, 7, 1];

    private function invoiceReminders(Carbon $today): int
    {
        $invoices = Invoice::query()
            ->with('project.client', 'payments')
            ->whereNotIn('status', ['draft', 'paid', 'void'])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', $today)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->outstanding() > 0);

        $count = 0;

        foreach ($invoices as $invoice) {
            $days = (int) $invoice->due_at->diffInDays($today);
            $stage = $this->stageFor($days);

            if ($stage === null) {
                continue;
            }

            $count += $this->send(
                'invoice'.($stage > 0 ? '.overdue.'.$stage : ''),
                $invoice,
                $today,
                $invoice->project->client->email,
                $this->invoiceMail($invoice, $stage),
                once: $stage > 0,
            );
        }

        return $count;
    }

    /**
     * Tahap tertinggi yang sudah dilewati. Mengembalikan 0 untuk hari-H, dan
     * null bila belum jatuh tempo atau sudah melewati tahap terakhir tanpa
     * ada tahap baru yang tercapai.
     */
    private function stageFor(int $daysOverdue): ?int
    {
        if ($daysOverdue === 0) {
            return 0;
        }

        foreach (self::OVERDUE_STAGES as $stage) {
            if ($daysOverdue >= $stage) {
                return $stage;
            }
        }

        return null;
    }

    private function invoiceMail(Invoice $invoice, int $stage): ReminderMail
    {
        $outstanding = 'Rp '.number_format($invoice->outstanding(), 0, ',', '.');

        [$title, $heading, $opening] = $stage === 0
            ? [
                'Invoice '.$invoice->number.' jatuh tempo hari ini',
                'Invoice jatuh tempo hari ini',
                null,
            ]
            : [
                'Pengingat: invoice '.$invoice->number.' belum terbayar',
                'Invoice sudah lewat jatuh tempo',
                'Invoice ini jatuh tempo '.$invoice->due_at->format('d M Y').
                    ' dan sampai sekarang belum kami terima pembayarannya.',
            ];

        return new ReminderMail(
            title: $title,
            heading: $heading,
            lines: array_filter([
                $opening,
                'Nomor: '.$invoice->number,
                'Project: '.$invoice->project->title,
                'Sisa tagihan: '.$outstanding,
                $stage > 0 ? 'Bila sudah dibayar, mohon abaikan email ini.' : null,
            ]),
            url: route('portal.projects.show', $invoice->project),
            urlLabel: 'Buka portal',
        );
    }

    /**
     * Kirim satu pengingat bila belum pernah dikirim.
     *
     * Baris log ditulis lebih dulu supaya unique index menengahi dua proses
     * yang berjalan bersamaan — yang kedua kalah di index dan berhenti tanpa
     * mengirim. Bila pengirimannya sendiri gagal, barisnya dihapus lagi
     * sehingga menjalankan ulang perintahnya benar-benar mencoba lagi.
     *
     * `$once` membedakan dua sifat pengingat. Pengingat harian boleh berulang
     * di hari yang berbeda, jadi cukup dikunci per tanggal. Susulan bertahap
     * hanya boleh sekali seumur tagihannya — tanpa ini, tahap yang sama
     * terkirim lagi setiap hari karena tanggalnya selalu baru.
     */
    private function send(string $type, Model $target, Carbon $today, ?string $to, ReminderMail $mail, bool $once = false): int
    {
        if (! $to) {
            return 0;
        }

        if ($once && $this->alreadySent($type, $target)) {
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

    private function alreadySent(string $type, Model $target): bool
    {
        return ReminderLog::where('type', $type)
            ->where('remindable_type', $target->getMorphClass())
            ->where('remindable_id', $target->getKey())
            ->exists();
    }
}
