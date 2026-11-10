<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\LoanTransaction;
use App\Models\ReturnRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('admin/reports/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'counts' => [
                'loans' => $this->loansQuery($from, $to)->count(),
                'returns' => $this->returnsQuery($from, $to)->count(),
                'damage' => $this->damageQuery($from, $to)->count(),
                'audit' => $this->auditQuery($from, $to)->count(),
            ],
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        return match ($type) {
            'loans' => $this->csv(
                "laporan-pinjaman-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['Referensi', 'Anggota', 'NRP', 'Status', 'Diserahkan', 'Tenggat', 'Selesai', 'Barang'],
                $this->loansQuery($from, $to),
                fn (LoanTransaction $loan): array => [
                    $loan->borrowRequest?->reference_no,
                    $loan->member?->name,
                    $loan->member?->nrp,
                    $loan->status,
                    $loan->checked_out_at?->toDateTimeString(),
                    $loan->due_at?->toDateTimeString(),
                    $loan->completed_at?->toDateTimeString(),
                    $loan->items
                        ->map(fn ($line) => trim($line->item->code.' '.$line->item->name.' x'.$line->quantity))
                        ->implode('; '),
                ],
            ),
            'returns' => $this->csv(
                "laporan-pengembalian-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['ID', 'Referensi pinjaman', 'Anggota', 'NRP', 'Status', 'Diajukan', 'Diverifikasi', 'Catatan'],
                $this->returnsQuery($from, $to),
                fn (ReturnRequest $return): array => [
                    $return->id,
                    $return->loanTransaction?->borrowRequest?->reference_no,
                    $return->loanTransaction?->member?->name,
                    $return->loanTransaction?->member?->nrp,
                    $return->status,
                    $return->requested_at->toDateTimeString(),
                    $return->verified_at?->toDateTimeString(),
                    $return->notes,
                ],
            ),
            'damage' => $this->csv(
                "laporan-kerusakan-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['ID', 'Kode barang', 'Barang', 'Unit', 'Jenis', 'Jumlah', 'Deskripsi', 'Status', 'Resolusi', 'Biaya', 'Vendor', 'Dilaporkan', 'Selesai', 'Pelapor', 'Penanggung jawab'],
                $this->damageQuery($from, $to),
                fn (DamageReport $report): array => [
                    $report->id,
                    $report->item?->code,
                    $report->item?->name,
                    $report->asset?->asset_code,
                    $report->type,
                    $report->quantity,
                    $report->description,
                    $report->status,
                    $report->resolution,
                    $report->cost,
                    $report->vendor,
                    $report->reported_at->toDateTimeString(),
                    $report->resolved_at?->toDateTimeString(),
                    $report->reporter?->name,
                    $report->resolver?->name,
                ],
            ),
            'audit' => $this->csv(
                "laporan-audit-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['ID', 'Waktu', 'Pelaku', 'Role', 'Tindakan', 'Entitas', 'Entitas ID', 'IP'],
                $this->auditQuery($from, $to),
                fn (AuditLog $log): array => [
                    $log->id,
                    $log->created_at->toDateTimeString(),
                    $log->actor_user_id,
                    $log->actor_role,
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->ip_address,
                ],
            ),
            default => abort(404),
        };
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = Carbon::parse($data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now())->endOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => 'Tanggal awal harus sebelum tanggal akhir.',
            ]);
        }

        return [$from, $to];
    }

    /**
     * @template TModel of Model
     *
     * @param  array<int, string>  $headers
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<int, mixed>  $map
     */
    private function csv(string $filename, array $headers, Builder $query, callable $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $query, $map): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');

            $query->chunk(500, function ($rows) use ($out, $map): void {
                foreach ($rows as $row) {
                    fputcsv($out, array_map($this->safeCell(...), $map($row)), ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(mixed $value): string|int|float|null
    {
        // Keep spreadsheet apps from evaluating user-controlled cells as formulas.
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @return Builder<LoanTransaction>
     */
    private function loansQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return LoanTransaction::query()
            ->with([
                'borrowRequest:id,reference_no',
                'member:id,name,nrp',
                'items.item:id,code,name',
            ])
            ->whereBetween('created_at', [$from, $to]);
    }

    /**
     * @return Builder<ReturnRequest>
     */
    private function returnsQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return ReturnRequest::query()
            ->with([
                'loanTransaction.borrowRequest:id,reference_no',
                'loanTransaction.member:id,name,nrp',
            ])
            ->whereBetween('requested_at', [$from, $to]);
    }

    /**
     * @return Builder<DamageReport>
     */
    private function damageQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DamageReport::query()
            ->with([
                'item:id,code,name',
                'asset:id,asset_code',
                'reporter:id,name',
                'resolver:id,name',
            ])
            ->whereBetween('reported_at', [$from, $to]);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function auditQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return AuditLog::query()->whereBetween('created_at', [$from, $to]);
    }
}
