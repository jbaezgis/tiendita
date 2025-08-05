<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:import {file : Path to CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import employees from CSV file and create corresponding users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Starting import from: {$filePath}");

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle); // Skip header row
        
        $importedCount = 0;
        $skippedCount = 0;

        while (($data = fgetcsv($handle)) !== false) {
            try {
                // Map CSV data
                $companyName = $data[0];
                $code = $data[1];
                $name = $data[2];
                $cedula = $data[3];
                $position = $data[4];
                $department = $data[5];

                // Find or create company
                $company = Company::firstOrCreate(
                    ['name' => $companyName],
                    ['description' => "Empresa {$companyName}"]
                );

                // Check if employee already exists
                $existingEmployee = Employee::where('cedula', $cedula)->first();
                if ($existingEmployee) {
                    $this->warn("Employee with cedula {$cedula} already exists. Skipping.");
                    $skippedCount++;
                    continue;
                }

                // Create employee
                $employee = Employee::create([
                    'company_id' => $company->id,
                    'code' => $code,
                    'name' => $name,
                    'cedula' => $cedula,
                    'position' => $position,
                    'department' => $department,
                    'category' => null, // Will be set later
                ]);

                // Check if user already exists
                $existingUser = User::where('cedula', $cedula)->first();
                if ($existingUser) {
                    // Update existing user with employee relationship
                    $existingUser->update(['employee_id' => $employee->id]);
                    $user = $existingUser;
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $name,
                        'cedula' => $cedula,
                        'email' => $this->generateEmail($name, $cedula),
                        'password' => Hash::make('12345678'),
                        'employee_id' => $employee->id,
                    ]);
                }

                // Assign default role
                $user->assignRole('empleado');

                $importedCount++;
                $this->info("Imported: {$name} ({$cedula})");

            } catch (\Exception $e) {
                $this->error("Error importing row: " . implode(',', $data) . " - " . $e->getMessage());
                $skippedCount++;
            }
        }

        fclose($handle);

        $this->info("Import completed!");
        $this->info("Imported: {$importedCount} employees");
        $this->info("Skipped: {$skippedCount} employees");

        return Command::SUCCESS;
    }

    private function generateEmail(string $name, string $cedula): string
    {
        $nameParts = explode(' ', $name);
        $firstName = Str::lower($nameParts[0] ?? '');
        $lastName = Str::lower($nameParts[1] ?? '');
        
        // Clean cedula for email
        $cleanCedula = str_replace(['-', ' '], '', $cedula);
        
        return "{$firstName}.{$lastName}.{$cleanCedula}@escolares.local";
    }
}
