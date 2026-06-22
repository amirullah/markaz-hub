<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemetaan ID Produk marketplace (mis. Product ID Shopee) -> SKU katalog.
 */
class ProductMarketplaceId extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'marketplace_product_id', 'sku'];
}
