<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Record one meaningful step in a project's history. Called explicitly
     * from controllers so the trail stays readable and intentional.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(Model $subject, string $action, string $description, array $properties = []): Activity
    {
        return Activity::create([
            'project_id' => self::projectIdFor($subject),
            'user_id' => Auth::id(),
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }

    private static function projectIdFor(Model $subject): ?int
    {
        if ($subject instanceof Project) {
            return $subject->getKey();
        }

        return $subject->project_id ?? null;
    }
}
