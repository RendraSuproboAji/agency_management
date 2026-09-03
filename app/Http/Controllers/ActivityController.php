<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\CaptureSession;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\ProcessingJob;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Riwayat lengkap, lintas jenis data.
     *
     * Halaman project hanya menampilkan aktivitas yang punya project_id, jadi
     * yang subjeknya klien atau peralatan tercatat tetapi tidak pernah tampil
     * di layar mana pun. Peralatan tidak punya halaman detail, dan riwayatnya
     * terbaca di sini alih-alih memaksa satu halaman baru hanya demi satu panel.
     */
    private const SUBJECTS = [
        'project' => Project::class,
        'client' => Client::class,
        'equipment' => Equipment::class,
        'deliverable' => Deliverable::class,
        'quotation' => Quotation::class,
        'invoice' => Invoice::class,
        'sesi' => CaptureSession::class,
        'job' => ProcessingJob::class,
        'lampiran' => Attachment::class,
    ];

    public function index(Request $request): Response
    {
        $subject = $request->query('subject');

        $activities = Activity::query()
            // Dimuat sejak awal: tanpa ini setiap baris menanyakan pelakunya sendiri.
            ->with('user', 'project')
            ->when(
                isset(self::SUBJECTS[$subject]),
                fn ($query) => $query->where('subject_type', self::SUBJECTS[$subject]),
            )
            // id sebagai pemutus seri: beberapa langkah bisa tercatat dalam
            // detik yang sama, dan created_at saja membuat urutannya sembarang.
            ->latest()
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $activity) => [
                ...$activity->only(['id', 'action', 'description']),
                'actor' => $activity->actorName(),
                'subject' => array_search($activity->subject_type, self::SUBJECTS, true) ?: 'lainnya',
                'project' => $activity->project?->only(['slug', 'title']),
                'created_at' => $activity->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'filters' => ['subject' => $subject],
            'subjects' => array_keys(self::SUBJECTS),
        ]);
    }
}
