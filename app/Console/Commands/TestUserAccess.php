<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TestUserAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:test-access {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test user access behavior and redirect logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $users = User::where('id', $userId)->get();
        } else {
            $users = User::all();
        }

        if ($users->isEmpty()) {
            $this->error('No users found.');
            return Command::FAILURE;
        }

        $this->info('Testing user access behavior:');
        $this->newLine();

        $headers = ['ID', 'Name', 'Roles', 'Employee', 'Dashboard Access', 'Public Orders', 'Login Redirect'];
        $rows = [];

        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->implode(', ');
            $hasEmployee = $user->employee ? 'Sí' : 'No';
            
            // Determine dashboard access
            $hasEmployeeRole = $user->hasRole(['empleado', 'supervisor']);
            $hasAdminRole = $user->hasRole(['Super Admin', 'admin']);
            
            if ($hasAdminRole) {
                $dashboardAccess = '✅ Completo (Admin)';
            } elseif ($hasEmployeeRole && $user->employee) {
                $dashboardAccess = '❌ Redirigido a public/orders';
            } elseif ($hasEmployeeRole && !$user->employee) {
                $dashboardAccess = '✅ Completo (Sin empleado)';
            } else {
                $dashboardAccess = '✅ Completo';
            }
            
            // Determine public orders access
            if ($hasEmployeeRole && $user->employee) {
                $publicOrdersAccess = '✅ Completo';
            } elseif ($hasEmployeeRole && !$user->employee) {
                $publicOrdersAccess = '❌ Bloqueado (Sin empleado)';
            } elseif ($hasAdminRole) {
                $publicOrdersAccess = '❌ Redirigido a dashboard';
            } else {
                $publicOrdersAccess = '❌ Sin acceso';
            }
            
            // Determine login redirect
            if ($hasAdminRole) {
                $loginRedirect = 'Dashboard';
            } elseif ($hasEmployeeRole && $user->employee) {
                $loginRedirect = 'Public Orders';
            } else {
                $loginRedirect = 'Dashboard';
            }
            
            $rows[] = [
                $user->id,
                $user->name,
                $roles ?: 'No roles',
                $hasEmployee,
                $dashboardAccess,
                $publicOrdersAccess,
                $loginRedirect,
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
} 