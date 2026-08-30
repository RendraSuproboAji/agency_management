<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_scheduled_for_tomorrow_reminds_its_crew_once(): void
    {
        Mail::fake();

        $crew = User::factory()->create(['email' => 'kru@studio.test']);
        CaptureSession::factory()->create([
            'crew_id' => $crew->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay()->setTime(9, 0),
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('kru@studio.test'));

        // Menjalankan ulang tidak boleh mengirim email kedua.
        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_a_session_further_out_is_not_reminded_yet(): void
    {
        Mail::fake();

        CaptureSession::factory()->create([
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(2),
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_cancelled_session_is_not_reminded(): void
    {
        Mail::fake();

        CaptureSession::factory()->create([
            'status' => 'cancelled',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_project_deadline_three_days_out_reminds_the_owner(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'pic@studio.test']);
        Project::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'capture',
            'deadline' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('pic@studio.test'));
    }

    public function test_an_invoice_due_today_reminds_the_client_but_a_settled_one_does_not(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'klien@contoh.test']);
        $project = Project::factory()->create(['client_id' => $client->id]);

        $invoice = Invoice::factory()->create([
            'project_id' => $project->id,
            'status' => 'sent',
            'amount' => 5_000_000,
            'due_at' => now()->toDateString(),
        ]);

        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('klien@contoh.test'));

        // Invoice yang sudah lunas tidak ikut ditagih.
        Mail::fake();
        $invoice->payments()->create([
            'amount' => 5_000_000,
            'paid_at' => now(),
            'method' => 'transfer',
        ]);

        $this->artisan('reminders:send')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
