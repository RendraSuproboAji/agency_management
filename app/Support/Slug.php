<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Build a slug for $source that is unique within $model's table,
     * ignoring $ignoreId (used when updating an existing record).
     *
     * $scope mempersempit pemeriksaan keunikan, misalnya scene yang hanya
     * perlu unik di dalam satu project.
     *
     * @param  class-string<Model>  $model
     * @param  (callable(Builder): mixed)|null  $scope
     */
    public static function uniqueFor(string $model, string $source, ?int $ignoreId = null, ?callable $scope = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (SoftDeleteAware::query($model)
            ->when($scope, fn ($query) => $scope($query))
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
