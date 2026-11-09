<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'system';

    protected $fillable = ['admin_id', 'action', 'group_id', 'payload', 'ip'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
