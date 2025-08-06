<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignMultipleRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-multiple-roles {user_id} {--roles=empleado,admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign multiple roles to a user for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $roles = explode(',', $this->option('roles'));
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Assigning roles to user: {$user->name} (ID: {$user->id})");
        $this->info("Email: {$user->email}");
        
        // Remove existing roles
        $user->syncRoles([]);
        
        // Assign new roles
        foreach ($roles as $role) {
            $role = trim($role);
            if ($role) {
                $user->assignRole($role);
                $this->info("✅ Assigned role: {$role}");
            }
        }
        
        // Display final roles
        $finalRoles = $user->getRoleNames()->toArray();
        $this->info("Final roles: " . implode(', ', $finalRoles));
        
        // Test the role access logic
        $hasEmployeeRole = $user->hasRole(['empleado', 'supervisor']);
        $hasAdminRole = $user->hasRole(['Super Admin', 'admin']);
        
        $this->info("Has employee role: " . ($hasEmployeeRole ? 'Yes' : 'No'));
        $this->info("Has admin role: " . ($hasAdminRole ? 'Yes' : 'No'));
        
        if ($hasEmployeeRole && $hasAdminRole) {
            $this->info("✅ User has multiple roles - should access both dashboard and public orders");
        } elseif ($hasEmployeeRole && !$hasAdminRole) {
            $this->info("👤 User is employee only - should access public orders only");
        } elseif ($hasAdminRole && !$hasEmployeeRole) {
            $this->info("👨‍💼 User is admin only - should access dashboard only");
        } else {
            $this->warn("⚠️ User has no relevant roles");
        }
        
        return 0;
    }
} 