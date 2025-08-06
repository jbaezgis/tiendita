<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\User;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportUserCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:import-categories {file : Path to Excel file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import user categories from Excel file (cedula, category_code)';

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

        $this->info("Starting category import from: {$filePath}");

        try {
            $data = Excel::toArray(new class implements ToArray, WithHeadingRow {
                public function array(array $array)
                {
                    return $array;
                }
            }, $filePath);

            if (empty($data) || empty($data[0])) {
                $this->error("No data found in the Excel file");
                return Command::FAILURE;
            }

            $rows = $data[0];
            $updatedCount = 0;
            $skippedCount = 0;
            $errors = [];

            $this->info("Processing " . count($rows) . " rows...");

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we skip header and arrays are 0-indexed

                try {
                    // Validate required columns
                    if (!isset($row['cedula']) || !isset($row['category'])) {
                        $errors[] = "Row {$rowNumber}: Missing required columns (cedula or category)";
                        $skippedCount++;
                        continue;
                    }

                    $cedula = trim($row['cedula']);
                    $categoryCode = trim($row['category']);

                    // Validate data
                    if (empty($cedula) || empty($categoryCode)) {
                        $errors[] = "Row {$rowNumber}: Empty cedula or category code";
                        $skippedCount++;
                        continue;
                    }

                    // Clean cedula (remove dashes and spaces)
                    $cleanCedula = $this->cleanCedula($cedula);
                    
                    // Find user by cedula (try both formats)
                    $user = User::where('cedula', $cedula)
                        ->orWhere('cedula', $cleanCedula)
                        ->first();
                        
                    if (!$user) {
                        $errors[] = "Row {$rowNumber}: User with cedula '{$cedula}' (cleaned: '{$cleanCedula}') not found";
                        $skippedCount++;
                        continue;
                    }

                    // Find category by code
                    $category = Category::where('code', $categoryCode)->first();
                    if (!$category) {
                        $errors[] = "Row {$rowNumber}: Category with code '{$categoryCode}' not found";
                        $skippedCount++;
                        continue;
                    }

                    // Update user category
                    $user->update(['category_id' => $category->id]);

                    $updatedCount++;
                    $this->info("Updated: {$user->name} ({$cedula}) -> Category: {$category->code}");

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    $skippedCount++;
                }
            }

            // Display results
            $this->newLine();
            $this->info("Import completed!");
            $this->info("Updated: {$updatedCount} users");
            $this->info("Skipped: {$skippedCount} rows");

            if (!empty($errors)) {
                $this->newLine();
                $this->warn("Errors encountered:");
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error reading Excel file: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clean cedula by removing dashes and spaces
     *
     * @param string $cedula
     * @return string
     */
    private function cleanCedula(string $cedula): string
    {
        // Remove dashes, spaces, and any other non-numeric characters except digits
        return preg_replace('/[^0-9]/', '', $cedula);
    }
} 