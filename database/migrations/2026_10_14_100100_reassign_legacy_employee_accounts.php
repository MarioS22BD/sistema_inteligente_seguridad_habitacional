<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BACKUP_TABLE = 'legacy_employee_role_backup';

    public function up(): void
    {
        Schema::create(self::BACKUP_TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('employee_role_id')->nullable();
            $table->unsignedBigInteger('user_role_id')->nullable();
            $table->boolean('had_employee_assignment')->default(false);
            $table->boolean('had_user_assignment')->default(false);
        });

        $employeeIds = DB::table('users')->where('rol', 'empleado')->pluck('id');
        if ($employeeIds->isEmpty()) {
            return;
        }

        $roleTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $employeeRoleId = DB::table($roleTable)
            ->where('name', 'empleado')
            ->where('guard_name', 'web')
            ->value('id');
        $userRoleId = DB::table($roleTable)
            ->where('name', 'usuario')
            ->where('guard_name', 'web')
            ->value('id');

        foreach ($employeeIds as $userId) {
            $hasEmployeeAssignment = $employeeRoleId !== null && DB::table($pivotTable)
                ->where('role_id', $employeeRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->exists();
            $hasUserAssignment = $userRoleId !== null && DB::table($pivotTable)
                ->where('role_id', $userRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->exists();

            DB::table(self::BACKUP_TABLE)->insert([
                'user_id' => $userId,
                'employee_role_id' => $employeeRoleId,
                'user_role_id' => $userRoleId,
                'had_employee_assignment' => $hasEmployeeAssignment,
                'had_user_assignment' => $hasUserAssignment,
            ]);
        }

        DB::table('users')->whereIn('id', $employeeIds)->update(['rol' => 'usuario']);

        if ($employeeRoleId !== null && $userRoleId !== null) {
            DB::table($pivotTable)
                ->where('role_id', $employeeRoleId)
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', $employeeIds)
                ->delete();

            foreach ($employeeIds as $userId) {
                DB::table($pivotTable)->insertOrIgnore([
                    'role_id' => $userRoleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        foreach (DB::table(self::BACKUP_TABLE)->get() as $backup) {
            DB::table('users')->where('id', $backup->user_id)->update(['rol' => 'empleado']);

            if ($backup->user_role_id !== null && ! $backup->had_user_assignment) {
                DB::table($pivotTable)
                    ->where('role_id', $backup->user_role_id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $backup->user_id)
                    ->delete();
            }

            if ($backup->employee_role_id !== null && $backup->had_employee_assignment) {
                DB::table($pivotTable)->insertOrIgnore([
                    'role_id' => $backup->employee_role_id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $backup->user_id,
                ]);
            }
        }

        Schema::dropIfExists(self::BACKUP_TABLE);
    }
};
