<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $report = $this->report($request);

        return $report instanceof JsonResponse ? $report : response()->json($report->summary());
    }

    /** The same report as a CSV file: one row per item sold, no customer details. */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $report = $this->report($request);

        if ($report instanceof JsonResponse) {
            return $report;
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // byte-order mark so Excel reads accents and Khmer correctly

            // The empty escape character is required on PHP 8.4+ and keeps plain RFC 4180 quoting.
            fputcsv($out, SalesReport::CSV_HEADER, ',', '"', '');
            foreach ($report->csvRows() as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $report->filename(), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Validate ?period=…&date=… and build the report. A bad request gets Laravel's usual 422
     * shape (message + errors) whatever the Accept header says, so a plain browser download
     * link fails the same way as the page's own requests.
     */
    private function report(Request $request): SalesReport|JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'period' => ['required', 'in:day,week,month'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        return SalesReport::for($request->query('period'), $request->query('date'));
    }
}
