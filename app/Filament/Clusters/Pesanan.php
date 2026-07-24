<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class Pesanan extends Cluster
{
    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $clusterBreadcrumb = 'Pesanan';

    protected static ?string $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pesanan';
}
