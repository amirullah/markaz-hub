<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

class Pesanan extends Cluster
{
    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $clusterBreadcrumb = 'Pesanan';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pesanan';
}
