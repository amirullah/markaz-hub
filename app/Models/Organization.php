<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant: satu Organization = satu seller. Data bisnis terikat ke organisasi ini.
 */
class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
