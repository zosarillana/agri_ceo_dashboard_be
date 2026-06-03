<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(protected DepartmentService $departmentService) {}

    /**
     * GET /api/departments
     */
    public function index(): JsonResponse
    {
        $departments = $this->departmentService->getAll();

        return response()->json(['departments' => $departments]);
    }

    /**
     * POST /api/departments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
        ]);

        $department = $this->departmentService->create($validated);

        return response()->json($department, 201);
    }

    /**
     * GET /api/departments/{department}
     */
    public function show(int $id): JsonResponse
    {
        $department = $this->departmentService->findById($id);

        return response()->json($department);
    }

    /**
     * PUT /api/departments/{department}
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name,'.$department->id],
        ]);

        $updated = $this->departmentService->update($department, $validated);

        return response()->json($updated);
    }

    /**
     * DELETE /api/departments/{department}
     */
    public function destroy(Department $department): JsonResponse
    {
        $this->departmentService->delete($department);

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    /**
     * GET /api/departments/{department}/users
     */
    public function users(Department $department): JsonResponse
    {
        $users = $this->departmentService->getUsers($department);

        return response()->json($users);
    }
}
