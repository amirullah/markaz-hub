{{-- Tombol "Masuk dengan Google" di bawah form login Filament --}}
<div style="margin-top:1rem">
    <div style="display:flex;align-items:center;gap:.75rem;margin:.5rem 0;color:#94a3b8;font-size:.8rem">
        <span style="flex:1;height:1px;background:#e2e8f0"></span>atau<span style="flex:1;height:1px;background:#e2e8f0"></span>
    </div>
    <a href="{{ route('google.redirect') }}"
       style="display:flex;align-items:center;justify-content:center;gap:.6rem;width:100%;
              padding:.6rem 1rem;border:1px solid #e2e8f0;border-radius:.5rem;
              background:#fff;color:#334155;font-weight:600;font-size:.9rem;text-decoration:none">
        <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Masuk dengan Google
    </a>
</div>
