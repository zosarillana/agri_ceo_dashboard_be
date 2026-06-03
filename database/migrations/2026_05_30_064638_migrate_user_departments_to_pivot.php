<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Insert unique departments from users table
        $departments = DB::table('users')
            ->select('department')
            ->whereNotNull('department')
            ->distinct()
            ->get();

        foreach ($departments as $dept) {
            DB::table('departments')->updateOrInsert([
                'name' => $dept->department,
            ]);
        }

        // 2. Attach users to departments
        $users = DB::table('users')->whereNotNull('department')->get();

        foreach ($users as $user) {
            $departmentId = DB::table('departments')
                ->where('name', $user->department)
                ->value('id');

            if ($departmentId) {
                DB::table('department_user')->insert([
                    'user_id' => $user->id,
                    'department_id' => $departmentId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('department_user')->delete();
        DB::table('departments')->delete();
    }
};