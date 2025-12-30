<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if permission already exists
        $existingPermission = DB::table('permissions')->where('name', 'system.manage')->first();
        
        if (!$existingPermission) {
            // Add system.manage permission
            DB::table('permissions')->insert([
                'name' => 'system.manage',
                'group' => 'system',
                'display_name' => 'Manage System',
                'description' => 'Can manage system updates, backups, and cache',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get Admin role ID - only if role exists
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        // Get the permission ID
        $permissionId = DB::table('permissions')->where('name', 'system.manage')->value('id');

        // Assign permission to Admin role only if both exist and not already assigned
        if ($adminRoleId && $permissionId) {
            $exists = DB::table('permission_role')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $permissionId)
                ->exists();
            
            if (!$exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('name', 'system.manage')->delete();
    }
};
