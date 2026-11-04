<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesReportController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $report = $this->report($request);

        return $report instanceof JsonResponse ? $report : response()->json($report->summary());
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
