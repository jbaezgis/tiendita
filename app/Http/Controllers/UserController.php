<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserController extends Controller
{
    public function downloadCategoryTemplate()
    {
        // Get sample users and categories
        $users = User::select('name', 'cedula')->limit(10)->get();
        $categories = Category::select('code')->get();

        if ($users->isEmpty()) {
            return back()->with('error', 'No users found in the system');
        }

        if ($categories->isEmpty()) {
            return back()->with('error', 'No categories found in the system');
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
        $filename = 'template_categorias_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new class($data) implements FromArray, WithHeadings {
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
        }, $filename);
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