<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $owner = Role::firstOrCreate(['name' => 'owner']);

        // Get all permissions
        $permissions = Permission::all();

        // Assign all permissions to both roles
        $superAdmin->syncPermissions($permissions);
        $owner->syncPermissions($permissions);

        //echo "Roles and permissions assigned successfully!\n";
    }
}
