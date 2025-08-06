<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestRoleAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:role-access {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test role access for users with multiple roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
            $this->testUserRoles($user);
        } else {
            $this->testAllUsers();
        }

        return 0;
    }

    private function testUserRoles(User $user)
    {
        $this->info("Testing user: {$user->name} (ID: {$user->id})");
        $this->info("Email: {$user->email}");
        
        $roles = $user->getRoleNames()->toArray();
        $this->info("Roles: " . implode(', ', $roles));
        
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
        
        $this->newLine();
    }

    private function testAllUsers()
    {
        $users = User::with('roles')->get();
        
        $this->info("Testing all users ({$users->count()} total):");
        $this->newLine();
        
        foreach ($users as $user) {
            $this->testUserRoles($user);
        }
        
        $this->info("Role access testing completed!");
    }
} 