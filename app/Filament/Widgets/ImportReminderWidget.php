<?php

namespace App\Filament\Widgets;

use App\Services\ImportSuggestion;
use Filament\Widgets\Widget;

/**
 * Banner di atas Dashboard: mengingatkan file apa yang perlu diupload + rentang tanggalnya.
 * Hanya muncul bila ADA saran (canView). Detail lengkap ada di halaman Impor.
 */
class ImportReminderWidget extends Widget
{
    protected string $view = 'filament.widgets.import-reminder';

    protected static ?int $sort = -4; // paling atas (di atas StatsOverview -3)

    protected int|string|array $columnSpan = 'full';

    /** Memo per-request agar canView() & getViewData() tak menghitung dua kali. */
    private static ?array $memo = null;

    private static function saran(): array
    {
        return self::$memo ??= app(ImportSuggestion::class)->compute();
    }

    public static function canView(): bool
    {
        return count(self::saran()) > 0;
    }

    protected function getViewData(): array
    {
        return ['saran' => self::saran()];
    }
}
