<?php

namespace App\Console\Commands;

use App\Services\OverdueService;
use Illuminate\Console\Command;

class MarkOverdueCommand extends Command
{
    protected $signature = 'inventory:overdue';

    protected $description = 'Tandai pinjaman terlambat, buat blokir anggota, dan batalkan reservasi yang tidak diambil.';

    public function handle(OverdueService $overdue): int
    {
        $expired = $overdue->expireUnclaimedReservations();
        $result = $overdue->markOverdue();

        $this->info("Kedaluwarsa reservasi: {$expired}");
        $this->info("Pinjaman ditandai terlambat: {$result['overdue']}");
        $this->info("Blok aktif: {$result['blocks']}");

        return self::SUCCESS;
    }
}
