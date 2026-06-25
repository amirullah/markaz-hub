<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Apa yang baru di MarkazHub</x-slot>
        <x-slot name="description">Ringkasan perubahan & fitur baru, per hari (terbaru di atas).</x-slot>

        <div style="display:flex;flex-direction:column;gap:1.25rem">
            @foreach ($changelog as $day)
                <div style="border-left:3px solid #2563eb;padding:.1rem 0 .1rem 1rem">
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <span style="background:#eff6ff;color:#1d4ed8;font-size:.78rem;font-weight:700;padding:.18rem .6rem;border-radius:999px;display:inline-flex;align-items:center;gap:.35rem"><svg style="width:.85rem;height:.85rem" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>{{ $day['date'] }}</span>
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
