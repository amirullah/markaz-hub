<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'marketplace', 'active', 'note'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
