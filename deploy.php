<?php

/**
 * Deploy script — untuk server dengan shell_exec() disabled (Plesk).
 * Upload via SFTP, akses via browser (wajib auth), atau jalankan via cron.
 *
 * Cara pakai:
 *   php deploy.php                        # jalan smua
 *   php deploy.php migrate                # hanya migrasi
 *   php deploy.php cache                  # hanya config/route/view cache
 *   php deploy.php seed <email>           # seed dummy data
 *
 * CATATAN: HAPUS / LINDUNGI file ini setelah deploy selesai!
 * Jangan biarkan di production tanpa proteksi.
 */

$cmd = $argv[1] ?? 'all';
$arg2 = $argv[2] ?? '';

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

function run(string $title, string $command, array $params = []): void
{
    echo "=== {$title} ===\n";
    $exit = Artisan::call($command, $params);
    echo Artisan::output() . "\n";
}

function section(string $msg): void
{
    echo "\n--- {$msg} ---\n";
}

// === SCRIPT UTAMA ===

section('DEPLOY MARKAZHUB');
echo 'Waktu: ' . now()->format('Y-m-d H:i:s') . "\n\n";

switch ($cmd) {
    case 'migrate':
        run('Migrasi database', 'migrate', ['--force' => true]);
        break;

    case 'cache':
        run('Config cache', 'config:cache');
        run('Route cache', 'route:cache');
        run('View cache', 'view:cache');
        break;

    case 'seed':
        if (! $arg2) {
            echo "Usage: php deploy.php seed <email>\n";
            exit(1);
        }
        run("Seed dummy data untuk {$arg2}", 'dummy:seed', ['email' => $arg2]);
        break;

    case 'all':
    default:
        run('Migrasi database', 'migrate', ['--force' => true]);
        run('Config cache', 'config:cache');
        run('Route cache', 'route:cache');
        run('View cache', 'view:cache');
        section('Storage link', 'storage:link');
        try {
            Artisan::call('storage:link');
            echo Artisan::output() . "\n";
        } catch (\Throwable $e) {
            echo "Storage link skipped (mungkin sudah ada): {$e->getMessage()}\n";
        }
        break;
}

section('SELESAI');
echo "Deploy selesai.\n";
