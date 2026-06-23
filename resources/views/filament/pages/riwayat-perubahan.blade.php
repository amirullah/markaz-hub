<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Apa yang baru di MarkazHub</x-slot>
        <x-slot name="description">Ringkasan perubahan & fitur baru, per hari (terbaru di atas).</x-slot>

        <div style="display:flex;flex-direction:column;gap:1.25rem">
            @foreach ($changelog as $day)
                <div style="border-left:3px solid #2563eb;padding:.1rem 0 .1rem 1rem">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <span style="background:#eff6ff;color:#1d4ed8;font-size:.78rem;font-weight:700;padding:.18rem .6rem;border-radius:999px">📅 {{ $day['date'] }}</span>
                    </div>
                    <ul style="list-style:disc;margin:.5rem 0 0 1.25rem;color:#475569;font-size:.88rem;line-height:1.65">
                        @foreach ($day['changes'] as $c)
                            <li style="margin-bottom:.2rem">{{ $c }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
