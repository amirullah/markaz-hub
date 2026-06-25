<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mkzTitle ?? 'Terjadi Kesalahan' }} — MarkazHub</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            padding: 1.5rem;
        }
        .card {
            width: 100%;
            max-width: 30rem;
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 1rem;
            padding: 2.25rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .brand {
            font-weight: 800;
            font-size: 1.05rem;
            color: #2563eb;
            letter-spacing: -0.01em;
        }
        .code {
            margin-top: 1.25rem;
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            color: #2563eb;
        }
        h1 {
            margin: 0.6rem 0 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }
        p {
            margin: 0 auto 1.5rem;
            font-size: 0.92rem;
            line-height: 1.6;
            color: #475569;
            max-width: 24rem;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            padding: 0.6rem 1.4rem;
            border-radius: 0.6rem;
            transition: background 0.15s;
        }
        .btn:hover { background: #1d4ed8; }
        .alt {
            display: block;
            margin-top: 0.9rem;
            font-size: 0.82rem;
            color: #94a3b8;
            text-decoration: none;
        }
        .alt:hover { color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">MarkazHub</div>
        <div class="code">{{ $mkzCode ?? '' }}</div>
        <h1>{{ $mkzHeading ?? 'Terjadi kesalahan' }}</h1>
        <p>{!! $mkzBody ?? '' !!}</p>
        <a href="/admin" class="btn">Kembali ke Dashboard</a>
        <a href="/admin/orders" class="alt">atau buka daftar Pesanan</a>
    </div>
</body>
</html>
