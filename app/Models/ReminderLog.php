<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'remindable_type', 'remindable_id', 'sent_on'])]
class ReminderLog extends Model
{
    protected function casts(): array
    {
        return ['sent_on' => 'date'];
    }
}
