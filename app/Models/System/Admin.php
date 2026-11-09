<?php

namespace App\Models\System;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Quản trị viên HIPOTECH đăng nhập /system, guard "system". */
class Admin extends Authenticatable
{
    protected $connection = 'system';

    protected $fillable = ['name', 'email', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}
