<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create company
        $company = Company::create([
            'name' => 'Empresa Ejemplo S.A.',
            'description' => 'Empresa de ejemplo para el sistema',
            'active' => true,
        ]);

        // Create categories
        $categories = [
            ['code' => 'OFI', 'salary_from' => 0, 'salary_to' => 25000, 'purchase_limit' => 500.00],
            ['code' => 'TEC', 'salary_from' => 25001, 'salary_to' => 50000, 'purchase_limit' => 1000.00],
            ['code' => 'MANT', 'salary_from' => 50001, 'salary_to' => 75000, 'purchase_limit' => 750.00],
            ['code' => 'LIM', 'salary_from' => 75001, 'salary_to' => 100000, 'purchase_limit' => 300.00],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // Create employees
        $employees = [
            ['name' => 'Juan Pérez', 'cedula' => '12345678', 'position' => 'Gerente', 'department' => 'Administración', 'category_id' => 1],
            ['name' => 'María García', 'cedula' => '23456789', 'position' => 'Analista', 'department' => 'IT', 'category_id' => 2],
            ['name' => 'Carlos López', 'cedula' => '34567890', 'position' => 'Técnico', 'department' => 'Mantenimiento', 'category_id' => 3],
            ['name' => 'Ana Rodríguez', 'cedula' => '45678901', 'position' => 'Auxiliar', 'department' => 'Limpieza', 'category_id' => 4],
            ['name' => 'Luis Martínez', 'cedula' => '56789012', 'position' => 'Supervisor', 'department' => 'Producción', 'category_id' => 1],
        ];

        foreach ($employees as $emp) {
            Employee::create(array_merge($emp, [
                'company_id' => $company->id,
                'code' => 'EMP' . substr($emp['cedula'], -4),
                'active' => true,
            ]));
        }

        // Create products
        $products = [
            ['code' => 'LAP001', 'description' => 'Laptop HP 15"', 'price' => 450.00],
            ['code' => 'MON001', 'description' => 'Monitor 24"', 'price' => 180.00],
            ['code' => 'TEC001', 'description' => 'Teclado inalámbrico', 'price' => 25.00],
            ['code' => 'MOUSE001', 'description' => 'Mouse óptico', 'price' => 15.00],
            ['code' => 'PAP001', 'description' => 'Papel A4 500 hojas', 'price' => 8.00],
            ['code' => 'PEN001', 'description' => 'Bolígrafos x12', 'price' => 5.00],
            ['code' => 'DESK001', 'description' => 'Escritorio de oficina', 'price' => 120.00],
            ['code' => 'CHAIR001', 'description' => 'Silla ergonómica', 'price' => 85.00],
            ['code' => 'CLEAN001', 'description' => 'Detergente limpiador', 'price' => 12.00],
            ['code' => 'TOOL001', 'description' => 'Kit de herramientas', 'price' => 45.00],
        ];

        foreach ($products as $prod) {
            Product::create($prod);
        }

        // Create admin user
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'cedula' => '00000000',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create sample orders for the last 30 days
        $this->createSampleOrders();
    }

    private function createSampleOrders()
    {
        $employees = Employee::all();
        $products = Product::all();
        $statuses = ['pending', 'approved', 'delivered', 'rejected'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        // Create orders for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $numOrders = rand(1, 5); // 1-5 orders per day

            for ($j = 0; $j < $numOrders; $j++) {
                $employee = $employees->random();
                $status = $statuses[array_rand($statuses)];
                $priority = $priorities[array_rand($priorities)];
                
                // Adjust status based on date (older orders are more likely to be completed)
                if ($i < 7) {
                    $status = $statuses[array_rand(['pending', 'approved'])];
                } elseif ($i < 14) {
                    $status = $statuses[array_rand(['approved', 'delivered'])];
                } else {
                    $status = $statuses[array_rand(['delivered', 'approved'])];
                }

                $order = Order::create([
                    'employee_id' => $employee->id,
                    'category_id' => $employee->category_id,
                    'order_date' => $date->toDateString(),
                    'subtotal' => 0,
                    'total' => 0,
                    'status' => $status,
                    'priority' => $priority,
                    'notes' => 'Pedido de muestra generado automáticamente',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);

                // Add 1-4 items to each order
                $numItems = rand(1, 4);
                $orderTotal = 0;

                for ($k = 0; $k < $numItems; $k++) {
                    $product = $products->random();
                    $quantity = rand(1, 5);
                    $subtotal = $product->price * $quantity;
                    $orderTotal += $subtotal;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $product->price,
                        'subtotal' => $subtotal,
                        'status' => $status === 'pending' ? 'pending' : 'approved',
                    ]);
                }

                // Update order totals
                $order->update([
                    'subtotal' => $orderTotal,
                    'total' => $orderTotal,
                ]);
            }
        }
    }
} 