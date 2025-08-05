<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos
        $permissions = [
            'view_employees',
            'create_employees',
            'edit_employees',
            'delete_employees',
            'view_orders',
            'create_orders',
            'edit_orders',
            'delete_orders',
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'manage_users',
            'manage_roles',
            'view_reports',
            'import_data',
            'export_data',
            'access_dashboard',
            'manage_system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear rol de empleado
        $employeeRole = Role::firstOrCreate(['name' => 'empleado']);
        $employeeRole->givePermissionTo([
            'view_products',
            'create_orders',
            'view_orders',
        ]);

        // Crear rol de supervisor
        $supervisorRole = Role::firstOrCreate(['name' => 'supervisor']);
        $supervisorRole->givePermissionTo([
            'view_employees',
            'view_orders',
            'edit_orders',
            'view_products',
            'view_reports',
            'export_data',
        ]);

        // Crear rol de admin
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view_employees',
            'create_employees',
            'edit_employees',
            'view_orders',
            'create_orders',
            'edit_orders',
            'view_products',
            'create_products',
            'edit_products',
            'view_reports',
            'export_data',
            'import_data',
            'access_dashboard',
        ]);

        // Crear rol de Super Admin
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo(Permission::all());
    }
}
