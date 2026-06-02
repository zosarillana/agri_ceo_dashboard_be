<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    /**
     * Get all departments (paginated).
     */
    public function getAll(): Collection
    {
        return Department::withCount('users')->orderBy('name')->get();
    }

    /**
     * Get a single department by ID.
     */
    public function findById(int $id): Department
    {
        return Department::withCount('users')->findOrFail($id);
    }

    /**
     * Create a new department.
     */
    public function create(array $data): Department
    {
        return Department::create([
            'name' => $data['name'],
        ]);
    }

    /**
     * Update an existing department.
     */
    public function update(Department $department, array $data): Department
    {
        $department->update([
            'name' => $data['name'],
        ]);

        return $department->fresh();
    }

    /**
     * Delete a department.
     */
    public function delete(Department $department): void
    {
        $department->delete();
    }

    /**
     * Get all users belonging to a department.
     */
    public function getUsers(Department $department): Collection
    {
        return $department->users()->get();
    }
}
