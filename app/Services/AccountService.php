<?php
// app/Services/AccountService.php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Valid status values for each account type
     */
    private const STATUS_RULES = [
        'receivable' => ['received', 'delayed'],
        'revenue'    => ['received', 'delayed'],
        'payable'    => ['unpaid', 'paid'],
        'expense'    => ['unpaid', 'paid'],
        'capex'      => ['unpaid', 'pending', 'paid'],
        'opex'       => ['unpaid', 'pending', 'paid'],
    ];

    /**
     * Default status for each account type
     */
    private const DEFAULT_STATUS = [
        'receivable' => 'received',
        'revenue'    => 'received',
        'payable'    => 'unpaid',
        'expense'    => 'unpaid',
        'capex'      => 'pending',
        'opex'       => 'pending',
    ];

    public function getAll(?string $from = null, ?string $to = null): Collection
    {
        $query = Account::query();
        
        if ($from && $to) {
            $query->createdBetween($from, $to);
        }
        
        return $query->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $accounts = $this->getAll($from, $to);

        return [
            'total_receivable' => (float) $accounts->whereIn('type', ['receivable', 'revenue'])->sum('amount'),
            'total_payable'    => (float) $accounts->whereIn('type', ['payable', 'expense'])->sum('amount'),
            'total_capex'      => (float) $accounts->where('type', 'capex')->sum('amount'),
            'total_opex'       => (float) $accounts->where('type', 'opex')->sum('amount'),
            'from'             => $from,
            'to'               => $to,
        ];
    }

    /**
     * Validate if a status is valid for a given account type
     */
    public function isValidStatus(string $type, string $status): bool
    {
        if (!isset(self::STATUS_RULES[$type])) {
            return false;
        }
        
        return in_array($status, self::STATUS_RULES[$type]);
    }

    /**
     * Get default status for an account type
     */
    public function getDefaultStatus(string $type): string
    {
        return self::DEFAULT_STATUS[$type] ?? 'pending';
    }

    /**
     * Validate and prepare account data before storage
     */
    private function prepareAccountData(array $data, bool $isUpdate = false): array
    {
        // Validate required fields
        if (!$isUpdate) {
            if (empty($data['description'])) {
                throw ValidationException::withMessages(['description' => 'Description is required']);
            }
            if (empty($data['type']) || !isset(self::STATUS_RULES[$data['type']])) {
                throw ValidationException::withMessages(['type' => 'Invalid account type']);
            }
        }

        $type = $data['type'] ?? null;
        
        // Handle status
        if (isset($data['status'])) {
            // Validate status is valid for the type
            if ($type && !$this->isValidStatus($type, $data['status'])) {
                $validStatuses = implode(', ', self::STATUS_RULES[$type]);
                throw ValidationException::withMessages([
                    'status' => "Invalid status '{$data['status']}' for type '{$type}'. Valid statuses: {$validStatuses}"
                ]);
            }
        } elseif (!$isUpdate && $type) {
            // Set default status for new records
            $data['status'] = $this->getDefaultStatus($type);
        }

        // Ensure amount is positive
        if (isset($data['amount']) && $data['amount'] <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than 0']);
        }

        // Clean up notes (empty string to null)
        if (isset($data['notes']) && $data['notes'] === '') {
            $data['notes'] = null;
        }

        // Ensure due_date is properly formatted or null
        if (isset($data['due_date']) && empty($data['due_date'])) {
            $data['due_date'] = null;
        }

        return $data;
    }

    /**
     * Store a new account record
     */
    public function store(array $data): Account
    {
        $preparedData = $this->prepareAccountData($data, false);
        
        return DB::transaction(function () use ($preparedData) {
            return Account::create($preparedData);
        });
    }

    /**
     * Update an existing account record
     */
    public function update(Account $account, array $data): Account
    {
        $preparedData = $this->prepareAccountData($data, true);
        
        return DB::transaction(function () use ($account, $preparedData) {
            $account->update($preparedData);
            return $account->fresh();
        });
    }

    /**
     * Delete an account record
     */
    public function delete(Account $account): void
    {
        DB::transaction(function () use ($account) {
            $account->delete();
        });
    }

    /**
     * Update only the status field with validation
     */
    public function updateStatus(Account $account, string $status): Account
    {
        // Validate the status for the account's type
        if (!$this->isValidStatus($account->type, $status)) {
            $validStatuses = implode(', ', self::STATUS_RULES[$account->type]);
            throw ValidationException::withMessages([
                'status' => "Invalid status '{$status}' for type '{$account->type}'. Valid statuses: {$validStatuses}"
            ]);
        }
        
        return DB::transaction(function () use ($account, $status) {
            $account->update(['status' => $status]);
            return $account->fresh();
        });
    }

    /**
     * Bulk store multiple accounts
     */
    public function bulkStore(array $accountsData): Collection
    {
        $created = collect();
        
        DB::transaction(function () use ($accountsData, &$created) {
            foreach ($accountsData as $data) {
                $created->push($this->store($data));
            }
        });
        
        return $created;
    }

    /**
     * Bulk update multiple accounts
     */
    public function bulkUpdate(array $updates): Collection
    {
        $updated = collect();
        
        DB::transaction(function () use ($updates, &$updated) {
            foreach ($updates as $update) {
                if (!isset($update['id'])) {
                    continue;
                }
                
                $account = Account::find($update['id']);
                if ($account) {
                    $updated->push($this->update($account, $update['data']));
                }
            }
        });
        
        return $updated;
    }

    /**
     * Get accounts by type with optional date range
     */
    public function getByType(string $type, ?string $from = null, ?string $to = null): Collection
    {
        if (!isset(self::STATUS_RULES[$type])) {
            throw ValidationException::withMessages(['type' => "Invalid account type: {$type}"]);
        }
        
        $query = Account::where('type', $type);
        
        if ($from && $to) {
            $query->createdBetween($from, $to);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Get accounts by status with optional date range
     */
    public function getByStatus(string $status, ?string $from = null, ?string $to = null): Collection
    {
        $query = Account::where('status', $status);
        
        if ($from && $to) {
            $query->createdBetween($from, $to);
        }
        
        return $query->orderBy('created_at')->get();
    }

    /**
     * Get all valid statuses for a specific account type
     */
    public function getValidStatuses(string $type): array
    {
        return self::STATUS_RULES[$type] ?? [];
    }

    /**
     * Get all account types with their valid statuses
     */
    public function getAccountTypesWithStatuses(): array
    {
        $result = [];
        foreach (self::STATUS_RULES as $type => $statuses) {
            $result[$type] = [
                'valid_statuses' => $statuses,
                'default_status' => self::DEFAULT_STATUS[$type],
            ];
        }
        return $result;
    }
}