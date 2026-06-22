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

        // Saat ada user login: PAKSA organization_id ke org user (timpa nilai apa pun
        // dari form/input) — mencegah membuat data untuk tenant lain.
        static::creating(function ($model) {
            if ($orgId = self::currentOrganizationId()) {
                $model->organization_id = $orgId;
            }
        });

        // Cegah memindahkan record ke organisasi lain lewat edit.
        static::updating(function ($model) {
            if (self::currentOrganizationId() && $model->isDirty('organization_id')) {
                $model->organization_id = $model->getOriginal('organization_id');
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
