<?php

// app/Http/Controllers/WorkforceController.php

namespace App\Http\Controllers;

use App\Services\WorkforceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkforceController extends Controller
{
    public function __construct(private readonly WorkforceService $workforceService) {}

    // ── GET /api/workforce ───────────────────────────────────────────────────
    // Returns latest record per department + summary totals.

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? null;
        $to   = $validated['to']   ?? null;

        return response()->json([
            'data'    => $this->workforceService->getLatest($from, $to),
            'summary' => $this->workforceService->getSummary($from, $to),
        ]);
    }

    // ── POST /api/workforce ──────────────────────────────────────────────────
    // Bulk upsert for a given date (defaults to today).

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_date'                => ['nullable', 'date_format:Y-m-d'],
            'rows'                       => ['required', 'array', 'min:1'],
            'rows.*.department_key'      => ['required', 'string', Rule::in(self::validKeys())],
            'rows.*.present'             => ['required', 'integer', 'min:0'],
            'rows.*.headcount'           => ['required', 'integer', 'min:0'],
            'rows.*.incidents'           => ['required', 'integer', 'min:0'],
        ]);

        // Ensure present never exceeds headcount per row
        $validator = Validator::make([], []);
        foreach ($validated['rows'] as $i => $row) {
            if ($row['present'] > $row['headcount']) {
                $validator->errors()->add(
                    "rows.{$i}.present",
                    'Present cannot exceed headcount.',
                );
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $records = $this->workforceService->storeBulk(
            $validated['rows'],
            $validated['record_date'] ?? null,
        );

        return response()->json([
            'message' => 'Workforce records saved.',
            'data'    => $records,
            'summary' => $this->workforceService->getSummary(
                $validated['record_date'] ?? null,
                $validated['record_date'] ?? null,
            ),
        ], 201);
    }

    // ── GET /api/workforce/summary ───────────────────────────────────────────
    // Summary totals only — lightweight for dashboard cards.

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(
            $this->workforceService->getSummary(
                $validated['from'] ?? null,
                $validated['to']   ?? null,
            ),
        );
    }

    // ── GET /api/workforce/history/{department_key} ──────────────────────────
    // Time-series for a single department — for trend charts.

    public function history(Request $request, string $departmentKey): JsonResponse
    {
        if (! in_array($departmentKey, self::validKeys(), true)) {
            return response()->json([
                'message' => 'Unknown department key.',
            ], 404);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json([
            'department_key' => $departmentKey,
            'data'           => $this->workforceService->getDepartmentHistory(
                $departmentKey,
                $validated['from'] ?? null,
                $validated['to']   ?? null,
            ),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static function validKeys(): array
    {
        return [
            // DEPARTMENT
            'opex', 'hr', 'it', 'sales', 'finance', 'gen_ops',
            'proc_rm', 'proc_nrm', 'proc_local_sales',
            'project', 'field_ops', 'business_ops', 'engineering',
            // DIRECT COST
            'proc_nuts_receiving', 'prod_dry_process',
            'prod_liquid_line', 'quality',
        ];
    }
}