<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant satu-database. Model yang memakai trait ini:
 *  - OTOMATIS tersaring ke organisasi user yang sedang login (global scope),
 *  - OTOMATIS terisi organization_id saat dibuat.
 * Saat tanpa user login (console/seeding/job), TIDAK disaring — atur eksplisit.
 *
 * Ini benteng isolasi data antar-seller. JANGAN lepas tanpa pengganti yang setara.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if ($orgId = self::currentOrganizationId()) {
                $builder->where($builder->getModel()->getTable() . '.organization_id', $orgId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->organization_id) && ($orgId = self::currentOrganizationId())) {
                $model->organization_id = $orgId;
            }
        });
    }

    protected static function currentOrganizationId(): ?int
    {
        $user = auth()->user();
        return $user?->organization_id ? (int) $user->organization_id : null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
