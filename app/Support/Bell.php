<?php

namespace App\Support;

use Filament\Notifications\Notification;

/**
 * Kirim notifikasi ke TOAST (sekejap) DAN LONCENG (tersimpan/persisten) sekaligus.
 * Dipakai untuk operasi penting (impor, kalibrasi, estimasi, backup, restore, kosongkan
 * data, auto-kategori) agar ada jejak di lonceng — bukan hanya toast yang cepat hilang.
 *
 * Catatan: lonceng memakai database notifications + QUEUE_CONNECTION=sync (shared hosting),
 * sehingga sendToDatabase langsung tampil tanpa worker.
 */
class Bell
{
    public static function send(Notification $notification): void
    {
        $notification->send(); // toast
        if ($user = auth()->user()) {
            $notification->sendToDatabase($user); // lonceng
        }
    }
}
