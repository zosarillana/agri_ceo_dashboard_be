<?php
// app/Http/Controllers/AccountController.php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(protected AccountService $accountService) {}

    /**
     * List all accounts with optional due date filter.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $accounts = $this->accountService->getAll(
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json(['data' => $accounts]);
    }

    /**
     * Get summary totals.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $summary = $this->accountService->getSummary(
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * Create a new account entry.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
            'type'        => 'required|in:receivable,revenue,payable,expense,capex,opex',
            'amount'      => 'required|numeric|min:0',
            'due_date'    => 'nullable|date',
            'notes'       => 'nullable|string',
            'is_paid'     => 'boolean',
        ]);

        $account = $this->accountService->store($data);

        return response()->json([
            'message' => 'Account created successfully.',
            'data'    => $account,
        ], 201);
    }

    /**
     * Update an account entry.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'description' => 'sometimes|string|max:255',
            'type'        => 'sometimes|in:receivable,revenue,payable,expense,capex,opex',
            'amount'      => 'sometimes|numeric|min:0',
            'due_date'    => 'nullable|date',
            'notes'       => 'nullable|string',
            'is_paid'     => 'boolean',
        ]);

        $account = $this->accountService->update($account, $data);

        return response()->json([
            'message' => 'Account updated successfully.',
            'data'    => $account,
        ]);
    }

    /**
     * Delete an account entry.
     */
    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->delete($account);

        return response()->json(['message' => 'Account deleted successfully.']);
    }

    /**
     * Mark an account as paid.
     */
    public function markPaid(Account $account): JsonResponse
    {
        $account = $this->accountService->markPaid($account);

        return response()->json([
            'message' => 'Account marked as paid.',
            'data'    => $account,
        ]);
    }
}