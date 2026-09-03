<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;
use App\Notifications\DeliverableReviewed;
use App\Notifications\DeliverableSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sebelum ini alur review sepenuhnya manual: staf menyerahkan hasil dan klien
 * tidak pernah diberi tahu; klien menilai dan PIC tidak pernah diberi tahu.
 */
class DeliverableNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Client, 2: Project} */
    private function scenario(): array
    {
        $owner = User::factory()->create(['email' => 'pic@studio.test']);
        $client = Client::factory()->withPortal()->create(['email' => 'klien@contoh.test']);
        $project = Project::factory()->create(['owner_id' => $owner->id, 'client_id' => $client->id]);

        return [$owner, $client, $project];
    }

    public function test_submitting_a_deliverable_tells_the_client(): void
    {
        Notification::fake();
        [, $client, $project] = $this->scenario();

        $this->actingAs($project->owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
        ])->assertRedirect();

        Notification::assertSentTo($client, DeliverableSubmitted::class);
    }

    public function test_a_draft_tells_nobody(): void
    {
        Notification::fake();
        [, , $project] = $this->scenario();

        $this->actingAs($project->owner)->post(route('deliverables.store', $project), [
            'title' => 'Masih draft',
            'type' => 'splat',
            'version' => 1,
            'status' => 'draft',
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_editing_an_already_submitted_deliverable_does_not_send_again(): void
    {
        Notification::fake();
        [, , $project] = $this->scenario();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($project->owner)->put(route('deliverables.update', [$project, $deliverable]), [
            'title' => 'Judul diperbaiki',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_a_client_without_portal_access_is_not_emailed(): void
    {
        Notification::fake();
        [, , $project] = $this->scenario();
        $project->client->forceFill(['portal_enabled' => false])->save();

        $this->actingAs($project->owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
        ])->assertRedirect();

        // Setiap notifikasi menautkan ke portal; tautan yang tidak bisa dibuka
        // lebih buruk daripada tidak mengirim apa pun.
        Notification::assertNothingSent();
    }

    public function test_client_approval_tells_the_project_owner(): void
    {
        Notification::fake();
        [$owner, $client, $project] = $this->scenario();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]))
            ->assertRedirect();

        Notification::assertSentTo($owner, DeliverableReviewed::class);
    }

    public function test_a_revision_request_carries_the_clients_note_to_the_owner(): void
    {
        Notification::fake();
        [$owner, $client, $project] = $this->scenario();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.revision', [$project, $deliverable]), [
                'review_note' => 'Sudut pandang lantai dua kurang tinggi.',
            ])->assertRedirect();

        Notification::assertSentTo($owner, DeliverableReviewed::class, function (DeliverableReviewed $notification) use ($owner) {
            return str_contains(
                implode(' ', $notification->toMail($owner)->lines),
                'Sudut pandang lantai dua kurang tinggi.',
            );
        });
    }

    /**
     * SMTP yang mati tidak boleh membuat staf kehilangan deliverable yang baru
     * diserahkan — pengirimannya sekunder terhadap aksi yang memicunya.
     */
    public function test_a_broken_mailer_does_not_lose_the_deliverable(): void
    {
        [, , $project] = $this->scenario();
        config(['mail.default' => 'tidak-ada-mailer']);

        $this->actingAs($project->owner)->post(route('deliverables.store', $project), [
            'title' => 'Splat lobi',
            'type' => 'splat',
            'version' => 1,
            'status' => 'submitted',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertSame(1, $project->deliverables()->count());
    }

    public function test_a_broken_mailer_does_not_lose_the_clients_approval(): void
    {
        [, $client, $project] = $this->scenario();
        $deliverable = Deliverable::factory()->create(['project_id' => $project->id, 'status' => 'submitted']);
        config(['mail.default' => 'tidak-ada-mailer']);

        $this->actingAs($client, 'client')
            ->put(route('portal.deliverables.approve', [$project, $deliverable]))
            ->assertRedirect();

        $this->assertSame('approved', $deliverable->fresh()->status);
    }
}
