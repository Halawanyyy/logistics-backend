<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'drivers.cash_closure.verify'],
            [
                'module' => 'Drivers',
                'name' => 'Verify Driver Cash Closures',
                'description' => 'Verify pending driver cash closures and sync received cash to treasury.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('slug', 'drivers.cash_closure.verify')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['admin', 'finance', 'super_admin'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'drivers.cash_closure.verify')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
