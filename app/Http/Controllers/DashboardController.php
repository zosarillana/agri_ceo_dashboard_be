<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->dashboardService->getDashboardStats(
                $request->input('date')
            ),
        ]);
    }
}