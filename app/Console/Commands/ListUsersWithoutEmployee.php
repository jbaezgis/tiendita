<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsersWithoutEmployee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list-without-employee';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List users who do not have an employee linked';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::whereNull('employee_id')->get();

        if ($users->isEmpty()) {
            $this->info('All users have employee links.');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} users without employee links:");
        $this->newLine();

        $headers = ['ID', 'Name', 'Email', 'Cédula', 'Roles'];
        $rows = [];

        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->implode(', ');
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->cedula,
                $roles ?: 'No roles',
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
} 