@php
    $rp = fn ($v) => $v === null ? '—' : 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp
<div style="display:flex;flex-direction:column;gap:.5rem">
    <div style="font-size:.85rem;color:#64748b;margin-bottom:.25rem">{{ $record->name }}</div>

    @forelse ($changes as $c)
        @php
            $naik = $c->old_price !== null && (float) $c->new_price > (float) $c->old_price;
            $turun = $c->old_price !== null && (float) $c->new_price < (float) $c->old_price;
        @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #eef2f7;border-radius:.6rem;padding:.6rem .8rem;background:#fff">
            <div style="font-size:.78rem;color:#64748b;min-width:120px">
                {{ $c->changed_at ? \Illuminate\Support\Carbon::parse($c->changed_at)->format('d M Y, H:i') : '—' }}
            </div>
            <div style="font-weight:600;flex:1;text-align:center">
                @if ($c->old_price !== null)
                    {{ $rp($c->old_price) }} <span style="color:#94a3b8">→</span> {{ $rp($c->new_price) }}
                @else
                    <span style="color:#64748b;font-weight:500">Harga awal:</span> {{ $rp($c->new_price) }}
                @endif
            </div>
            <div style="min-width:110px;text-align:right">
                @if ($naik)
                    <span style="color:#dc2626;font-weight:700">▲ +{{ $rp((float) $c->new_price - (float) $c->old_price) }}</span>
                @elseif ($turun)
                    <span style="color:#16a34a;font-weight:700">▼ −{{ $rp((float) $c->old_price - (float) $c->new_price) }}</span>
                @else
                    <span style="color:#94a3b8">—</span>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align:center;color:#94a3b8;padding:1.5rem">
            Belum ada riwayat perubahan harga untuk produk ini.<br>
            <span style="font-size:.8rem">Riwayat akan terisi otomatis setiap kali Anda meng-upload file master produk Jakmall yang baru.</span>
        </div>
    @endforelse
</div>
