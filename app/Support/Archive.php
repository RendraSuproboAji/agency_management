<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arsip berjenjang untuk soft delete.
 *
 * Foreign key `cascadeOnDelete` hanya bekerja pada hapus fisik, jadi
 * mengarsipkan klien tanpa penanganan khusus akan meninggalkan project yatim
 * yang masih terlihat di daftar. Di sini induk dan turunannya diarsipkan dengan
 * `deleted_at` yang sama persis, lalu pemulihan hanya menyentuh turunan
 * ber-`deleted_at` identik — sehingga anak yang sudah diarsipkan lebih dulu
 * tetap tinggal di arsip.
 */
class Archive
{
    public static function archiveClient(Client $client): Carbon
    {
        $at = Carbon::now();

        DB::transaction(function () use ($client, $at) {
            foreach ($client->projects()->get() as $project) {
                self::archiveProjectAt($project, $at);
            }

            self::stamp($client, $at);
        });

        return $at;
    }

    public static function archiveProject(Project $project): Carbon
    {
        $at = Carbon::now();

        DB::transaction(fn () => self::archiveProjectAt($project, $at));

        return $at;
    }

    public static function restoreClient(Client $client): void
    {
        $at = $client->deleted_at;

        DB::transaction(function () use ($client, $at) {
            $client->restore();

            foreach (self::matching($client->projects()->onlyTrashed(), $at)->get() as $project) {
                self::restoreProjectAt($project, $at);
            }
        });
    }

    public static function restoreProject(Project $project): void
    {
        $at = $project->deleted_at;

        DB::transaction(fn () => self::restoreProjectAt($project, $at));
    }

    private static function archiveProjectAt(Project $project, Carbon $at): void
    {
        foreach ($project->invoices()->get() as $invoice) {
            self::stampMany($invoice->payments(), $at);
            self::stamp($invoice, $at);
        }

        self::stampMany($project->quotations(), $at);
        self::stampMany($project->deliverables(), $at);
        self::stamp($project, $at);
    }

    private static function restoreProjectAt(Project $project, ?Carbon $at): void
    {
        $project->restore();

        self::matching($project->quotations()->onlyTrashed(), $at)->restore();
        self::matching($project->deliverables()->onlyTrashed(), $at)->restore();

        foreach (self::matching($project->invoices()->onlyTrashed(), $at)->get() as $invoice) {
            $invoice->restore();
            self::matching($invoice->payments()->onlyTrashed(), $at)->restore();
        }
    }

    /** Tandai satu baris terarsip pada waktu yang ditentukan. */
    private static function stamp(Model $model, Carbon $at): void
    {
        $model->forceFill(['deleted_at' => $at])->saveQuietly();
    }

    /** Tandai seluruh isi relasi yang belum terarsip. */
    private static function stampMany(mixed $relation, Carbon $at): void
    {
        $relation->whereNull('deleted_at')->update(['deleted_at' => $at]);
    }

    /** Batasi ke baris yang diarsipkan bersama induknya. */
    private static function matching(mixed $query, ?Carbon $at): mixed
    {
        return $query->where('deleted_at', $at);
    }
}
