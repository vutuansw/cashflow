<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;

/** Loại thu chi của nhóm, xem plan/03 mục 1. */
class Category extends Model
{
    use BelongsToGroup;

    protected $fillable = ['type', 'name', 'icon', 'color', 'is_system', 'sort_order', 'archived_at'];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'is_system' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
