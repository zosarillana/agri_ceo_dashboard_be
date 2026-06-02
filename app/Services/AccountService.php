<?php
// app/Services/AccountService.php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Collection;

class AccountService
{
    /**
     * Get all accounts, optionally filtered by due date range.
     */
    public function getAll(?string $from = null, ?string $to = null): Collection
    {
        return Account::dueBetween($from, $to)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Summary totals grouped by type.
     */
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
     * Create a single account entry.
     */
    public function store(array $data): Account
    {
        return Account::create($data);
    }

    /**
     * Update an account entry.
     */
    public function update(Account $account, array $data): Account
    {
        $account->update($data);
        return $account->fresh();
    }

    /**
     * Delete an account entry.
     */
    public function delete(Account $account): void
    {
        $account->delete();
    }

    /**
     * Mark an account as paid.
     */
    public function markPaid(Account $account): Account
    {
        $account->update(['is_paid' => true]);
        return $account->fresh();
    }
}