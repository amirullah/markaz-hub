<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'type', 'note'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
