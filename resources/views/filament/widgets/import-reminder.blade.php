<div>
    @php
        $penting = collect($saran)->where('urgency', 'high')->count();
        $lain = count($saran) - $penting;
        $url = \App\Filament\Pages\ImportData::getUrl();
        $parts = [];
        if ($penting) {
            $parts[] = "<strong>{$penting} Penting</strong> (laba belum final)";
        }
        if ($lain) {
            $parts[] = "{$lain} melengkapi data";
        }
        $sub = implode(' · ', $parts);
    @endphp
    <a href="{{ $url }}" wire:navigate style="display:block;text-decoration:none;border:1px solid #fcd34d;background:#fffbeb;border-radius:.85rem;padding:.85rem 1.1rem">
        <div style="display:flex;align-items:center;gap:.8rem">
            <div style="width:2.4rem;height:2.4rem;flex:none;display:flex;align-items:center;justify-content:center;border-radius:.6rem;background:#fef3c7">
                <svg style="width:1.3rem;height:1.3rem;color:#b45309" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 7.5 12 3m0 0L7.5 7.5M12 3v13.5"/></svg>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:700;color:#92400e;font-size:.95rem">{{ count($saran) }} file perlu segera diupload</div>
                <div style="font-size:.8rem;color:#78350f">{!! $sub !!} — klik untuk lihat file, jumlah &amp; rentang tanggal.</div>
            </div>
            <span style="background:#2563eb;color:#fff;font-weight:600;font-size:.82rem;padding:.5rem 1rem;border-radius:.55rem;white-space:nowrap">Buka Impor →</span>
        </div>
    </a>
</div>
