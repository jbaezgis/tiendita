<?php

namespace App\Exports;

use App\Models\OrderItem;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ProductsSalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $period;
    protected $category;
    protected $search;

    public function __construct($period = 30, $category = '', $search = '')
    {
        $this->period = $period;
        $this->category = $category;
        $this->search = $search;
    }

    public function collection()
    {
        $days = (int) $this->period;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $query = OrderItem::whereHas('order', function ($query) use ($startDate) {
            $query->whereBetween('created_at', [$startDate, now()]);
        })
        ->with(['product.category', 'order'])
        ->selectRaw('
            product_id,
            SUM(quantity) as total_quantity,
            SUM(subtotal) as total_amount,
            COUNT(DISTINCT order_id) as order_count,
            MAX(order_items.created_at) as last_sale,
            AVG(price) as avg_price
        ')
        ->groupBy('product_id');

        // Apply filters
        if ($this->category) {
            $query->whereHas('product', function ($q) {
                $q->where('product_category_id', $this->category);
            });
        }

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        return $query->orderByDesc('total_quantity')->get();
    }

    public function headings(): array
    {
        return [
            'Código',
            'Producto',
            'Categoría',
            'Cantidad Total Vendida',
            'Monto Total Vendido (RD$)',
            'Precio Unitario Promedio (RD$)',
            'Número de Pedidos',
            'Última Venta'
        ];
    }

    public function map($item): array
    {
        return [
            $item->product->code,
            $item->product->description,
            $item->product->category ? $item->product->category->name : 'Sin categoría',
            $item->total_quantity,
            number_format($item->total_amount, 2),
            number_format($item->avg_price, 2),
            $item->order_count,
            $item->last_sale ? Carbon::parse($item->last_sale)->format('d/m/Y H:i') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styles
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Data styles
        $sheet->getStyle('A2:H' . ($sheet->getHighestRow()))->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Number columns alignment
        $sheet->getStyle('D:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add borders
        $sheet->getStyle('A1:H' . $sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        // Alternate row colors
        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F9FAFB'));
            }
        }

        return $sheet;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Código
            'B' => 40, // Producto
            'C' => 20, // Categoría
            'D' => 20, // Cantidad
            'E' => 25, // Monto Total
            'F' => 25, // Precio Promedio
            'G' => 15, // Pedidos
            'H' => 20, // Última Venta
        ];
    }
}
