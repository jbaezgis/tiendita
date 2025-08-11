<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Console\Command;

class LinkUserToEmployee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:link-employee {user_id} {employee_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link a user to an employee record';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $employeeId = $this->argument('employee_id');

        // Find the user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return Command::FAILURE;
        }

        // Find the employee
        $employee = Employee::find($employeeId);
        if (!$employee) {
            $this->error("Employee with ID {$employeeId} not found.");
            return Command::FAILURE;
        }

        // Check if employee is already linked to another user
        $existingUser = User::where('employee_id', $employeeId)->first();
        if ($existingUser && $existingUser->id !== $userId) {
            $this->error("Employee {$employee->name} is already linked to user {$existingUser->name} (ID: {$existingUser->id}).");
            return Command::FAILURE;
        }

        // Update the user
        $user->update(['employee_id' => $employeeId]);

        $this->info("Successfully linked user '{$user->name}' to employee '{$employee->name}'.");
        
        return Command::SUCCESS;
    }
} 