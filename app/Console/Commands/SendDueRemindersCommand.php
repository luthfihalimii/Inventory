<?php

namespace App\Console\Commands;

use App\Models\LoanTransaction;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDueRemindersCommand extends Command
{
    protected $signature = 'inventory:due-reminders';

    protected $description = 'Kirim pengingat H-1 untuk pinjaman yang jatuh tempo besok.';

    public function handle(NotificationService $notifications): int
    {
        $start = now()->addDay()->startOfDay();
        $end = now()->addDay()->endOfDay();

        $loans = LoanTransaction::query()
            ->where('status', 'borrowed')
            ->whereNull('due_reminder_sent_at')
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('id')
            ->get();

        $sent = 0;

        foreach ($loans as $loan) {
            /** @var array{id: int, member_id: int}|null $notify */
            $notify = DB::transaction(function () use ($loan): ?array {
                $locked = LoanTransaction::whereKey($loan->id)->lockForUpdate()->first();

                // Re-check under the lock: the loan may have been returned or flagged since the query.
                if ($locked === null || $locked->status !== 'borrowed' || $locked->due_reminder_sent_at !== null) {
                    return null;
                }

                $locked->update(['due_reminder_sent_at' => now()]);

                return ['id' => $locked->id, 'member_id' => $locked->member_id];
            });

            if ($notify === null) {
                continue;
            }

            $sent++;

            $notifications->notifyMember(
                $notify['member_id'],
                'loan.due_reminder',
                'Pengingat tenggat besok',
                'Batas pengembalian besok. Kembalikan barang tepat waktu agar pengajuan berikutnya tidak diblokir.',
                ['loan_transaction_id' => $notify['id']],
            );
        }

        $this->info("Pengingat H-1 terkirim: {$sent}");

        return self::SUCCESS;
    }
}
