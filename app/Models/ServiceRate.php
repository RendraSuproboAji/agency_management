<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_type', 'unit', 'label', 'unit_price', 'min_charge', 'active'])]
class ServiceRate extends Model
{
    /** Satuan yang bisa dihitung otomatis dari data project. */
    public const UNITS = ['sqm', 'scene', 'session', 'paket'];

    public const UNIT_LABELS = [
        'sqm' => 'per m²',
        'scene' => 'per scene',
        'session' => 'per sesi',
        'paket' => 'per paket',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_charge' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
