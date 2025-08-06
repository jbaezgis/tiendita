<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\User;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GenerateCategoryTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-category-template {--output=template_categorias.xlsx : Output filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Excel template for user category import';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputFile = $this->option('output');
        $outputPath = storage_path('app/private/public/' . $outputFile);

        // Get sample users and categories
        $users = User::select('name', 'cedula')->limit(10)->get();
        $categories = Category::select('code')->get();

        if ($users->isEmpty()) {
            $this->error('No users found in the system');
            return Command::FAILURE;
        }

        if ($categories->isEmpty()) {
            $this->error('No categories found in the system');
            return Command::FAILURE;
        }

        // Generate sample data
        $data = [];
        foreach ($users as $user) {
            $randomCategory = $categories->random();
            $data[] = [
                'cedula' => $this->formatCedulaWithDashes($user->cedula),
                'category' => $randomCategory->code,
            ];
        }

        // Create Excel file
        Excel::store(new class($data) implements FromArray, WithHeadings {
            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return ['cedula', 'category'];
            }
        }, 'public/' . $outputFile);

        $this->info("Template generated successfully!");
        $this->info("File saved to: {$outputPath}");
        $this->info("Total users in template: " . count($data));
        $this->info("Available categories: " . $categories->pluck('code')->implode(', '));

        return Command::SUCCESS;
    }

    /**
     * Format cedula with dashes (XXX-XXXXXXX-X)
     *
     * @param string $cedula
     * @return string
     */
    private function formatCedulaWithDashes(string $cedula): string
    {
        // Clean the cedula first (remove any existing dashes)
        $cleanCedula = preg_replace('/[^0-9]/', '', $cedula);
        
        // Format as XXX-XXXXXXX-X
        if (strlen($cleanCedula) === 11) {
            return substr($cleanCedula, 0, 3) . '-' . substr($cleanCedula, 3, 7) . '-' . substr($cleanCedula, 10, 1);
        }
        
                // If not 11 digits, return as is
        return $cedula;
    }
} 