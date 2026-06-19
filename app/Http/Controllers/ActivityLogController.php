<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()
            ->with(['causer']) // who did it
            ->latest();

        // ── Filter by log type (audit/auth/activity)
        $query->when($request->filled('log_name'), function ($q) use ($request) {
            $q->where('log_name', $request->log_name);
        });

        // ── Filter by user
        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('causer_id', $request->user_id);
        });

        // ── Filter by action (created, updated, deleted, login, etc.)
        $query->when($request->filled('description'), function ($q) use ($request) {
            $q->where('description', $request->description);
        });

        // ── Filter by model type (Product, Sale, etc.)
        $query->when($request->filled('subject_type'), function ($q) use ($request) {
            $q->where('subject_type', 'like', '%' . $request->subject_type . '%');
        });

        // ── Filter by subject ID
        $query->when($request->filled('subject_id'), function ($q) use ($request) {
            $q->where('subject_id', $request->subject_id);
        });

        // ── Date range filter (very important for logs)
        $query->when($request->filled('from'), function ($q) use ($request) {
            $q->whereDate('created_at', '>=', $request->from);
        });

        $query->when($request->filled('to'), function ($q) use ($request) {
            $q->whereDate('created_at', '<=', $request->to);
        });

        // ── Optional search (useful for admin UI)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($sub) use ($request) {
                $sub->where('description', 'like', '%' . $request->search . '%')
                    ->orWhere('log_name', 'like', '%' . $request->search . '%')
                    ->orWhere('subject_type', 'like', '%' . $request->search . '%');
            });
        });

        return $query->paginate(
            $request->get('per_page', 20)
        );
    }
}