<?php

namespace LaraSlice\Slices\Users\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LaraSlice\Core\Security\Traits\HasSlicePermissions;
use LaraSlice\Slices\Roles\Models\Role;

class User extends Authenticatable
{
    use Notifiable, HasSlicePermissions;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
