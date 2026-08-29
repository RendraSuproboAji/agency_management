<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class DocumentNumber
{
    /**
     * Build the next sequential document number for the given year,
     * e.g. QUO/2026/0001. Sequence restarts every year.
     *
     * @param  class-string<Model>  $model
     */
    public static function next(string $model, string $prefix, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $scope = sprintf('%s/%d/', $prefix, $year);

        $last = SoftDeleteAware::query($model)
            ->where('number', 'like', $scope.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($scope))) + 1 : 1;

        return $scope.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
