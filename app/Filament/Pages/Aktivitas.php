<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Aktivitas extends Page
{
    protected string $view = 'filament.pages.aktivitas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Aktivitas';

    protected static ?string $title = 'Log Aktivitas';

    protected static ?int $navigationSort = 8;

    public function getViewData(): array
    {
        // Ter-scope ke organisasi user (global scope ActivityLog).
        $activities = ActivityLog::query()
            ->with('causer')
            ->latest()
            ->limit(100)
            ->get();

        return compact('activities');
    }
}
