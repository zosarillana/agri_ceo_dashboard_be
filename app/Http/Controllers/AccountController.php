<?php
// app/Http/Controllers/AccountController.php

namespace App\Http\Controllers;

use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * Get accounts with optional date range
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $from = $request->input('from');
            $to = $request->input('to');
            
            $accounts = $this->accountService->getAll($from, $to);
            
            return response()->json([
                'success' => true,
                'data' => $accounts,
                'meta' => [
                    'total' => $accounts->count(),
                    'from' => $from,
                    'to' => $to,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $from = $request->input('from');
            $to = $request->input('to');
            
            $summary = $this->accountService->getSummary($from, $to);
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new account
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|max:255',
                'type' => 'required|in:receivable,revenue,payable,expense,capex,opex',
                'amount' => 'required|numeric|min:0.01',
                'due_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
                'status' => 'nullable|string',
            ]);
            
            $account = $this->accountService->store($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $account
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing account
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $account = \App\Models\Account::findOrFail($id);
            
            $validated = $request->validate([
                'description' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:receivable,revenue,payable,expense,capex,opex',
                'amount' => 'sometimes|required|numeric|min:0.01',
                'due_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
                'status' => 'nullable|string',
            ]);
            
            $account = $this->accountService->update($account, $validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => $account
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an account
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $account = \App\Models\Account::findOrFail($id);
            $this->accountService->delete($account);
            
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update only the status of an account
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string'
            ]);
            
            $account = \App\Models\Account::findOrFail($id);
            $account = $this->accountService->updateStatus($account, $validated['status']);
            
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => $account
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk store multiple accounts
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'accounts' => 'required|array',
                'accounts.*.description' => 'required|string|max:255',
                'accounts.*.type' => 'required|in:receivable,revenue,payable,expense,capex,opex',
                'accounts.*.amount' => 'required|numeric|min:0.01',
                'accounts.*.due_date' => 'nullable|date',
                'accounts.*.notes' => 'nullable|string|max:1000',
                'accounts.*.status' => 'nullable|string',
            ]);
            
            $accounts = $this->accountService->bulkStore($validated['accounts']);
            
            return response()->json([
                'success' => true,
                'message' => 'Accounts created successfully',
                'data' => $accounts,
                'meta' => [
                    'count' => $accounts->count()
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account types configuration (for frontend)
     */
    public function getTypesConfig(): JsonResponse
    {
        try {
            $config = $this->accountService->getAccountTypesWithStatuses();
            
            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch types configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}