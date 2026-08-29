<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteAware
{
    /**
     * Query a model including archived rows when it uses soft deletes.
     *
     * Uniqueness checks must span the same rows as the database's unique
     * index, and that index does not care about deleted_at. Querying without
     * this leaves an archived row invisible, so the "free" slug or document
     * number it holds gets handed out again and the insert dies on the
     * constraint.
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    public static function query(string $model): Builder
    {
        $query = $model::query();

        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $query->withTrashed()
            : $query;
    }
}
