<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $connection = 'system';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)?->value ?? $default;
    }
}
