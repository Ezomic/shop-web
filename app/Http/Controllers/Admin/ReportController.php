<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly SalesReport $report) {}

    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('admin/Reports', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'month' => $this->report->totals(now()->startOfMonth()->toImmutable(), now()->toImmutable()),
            'year' => $this->report->totals(now()->startOfYear()->toImmutable(), now()->toImmutable()),
            'selection' => $this->report->totals($from, $to),
            'refunded' => $this->report->refunded($from, $to),
            'bestSellers' => $this->report->bestSellers($from, $to),
            'quarters' => $this->report->byQuarterAndCountry(now()->year),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $filename = 'shop-sales-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->report->headers());

            foreach ($this->report->rows($from, $to) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfYear()->toImmutable();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay()->toImmutable();

        return [$from, $to];
    }
}
