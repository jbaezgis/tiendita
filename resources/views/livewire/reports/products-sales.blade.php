<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Exports\ProductsSalesExport;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $selectedPeriod = '30';
    public $selectedCategory = '';
    public $search = '';
    public $sortBy = 'total_quantity';
    public $sortDirection = 'desc';
    public $perPage = 25;
    public $page = 1;

    public function mount()
    {
        // Default to last 30 days
    }

    public function updatedSelectedPeriod()
    {
        $this->page = 1;
    }

    public function updatedSelectedCategory()
    {
        $this->page = 1;
    }

    public function updatedSearch()
    {
        $this->page = 1;
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function exportToExcel()
    {
        $filename = 'reporte_productos_vendidos_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(
            new ProductsSalesExport($this->selectedPeriod, $this->selectedCategory, $this->search),
            $filename
        );
    }

    public function getProductsSalesDataProperty()
    {
        $days = (int) $this->selectedPeriod;
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
        if ($this->selectedCategory) {
            $query->whereHas('product', function ($q) {
                $q->where('product_category_id', $this->selectedCategory);
            });
        }

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        $items = $query->get();

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'code' => $item->product->code,
                'product' => $item->product->description,
                'category' => $item->product->category ? $item->product->category->name : 'Sin categoría',
                'total_quantity' => $item->total_quantity,
                'total_amount' => (float) $item->total_amount,
                'avg_price' => (float) $item->avg_price,
                'order_count' => $item->order_count,
                'last_sale' => $item->last_sale ? Carbon::parse($item->last_sale)->format('d/m/Y H:i') : 'N/A',
            ];
        }

        return $data;
    }

    public function getPaginatedDataProperty()
    {
        $data = $this->productsSalesData;
        
        if ($this->perPage == 0) {
            return $data;
        }
        
        $total = count($data);
        $currentPage = $this->page ?? 1;
        $offset = ($currentPage - 1) * $this->perPage;
        
        return array_slice($data, $offset, $this->perPage);
    }

    public function getTotalStatsProperty()
    {
        $data = $this->productsSalesData;
        
        return [
            'total_products' => count($data),
            'total_quantity' => collect($data)->sum('total_quantity'),
            'total_amount' => collect($data)->sum('total_amount'),
            'total_orders' => collect($data)->sum('order_count'),
        ];
    }

    public function with(): array
    {
        return [
            'productCategories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
            'paginatedData' => $this->paginatedData,
            'totalStats' => $this->totalStats,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Reporte de Productos Vendidos</flux:heading>
            <flux:subheading>Análisis detallado de ventas por producto</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            <flux:button 
                icon="arrow-down-tray" 
                variant="primary" 
                color="green" 
                wire:click="exportToExcel"
                size="sm"
            >
                Exportar a Excel
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <flux:card>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:select wire:model.live="selectedPeriod" label="Período" size="sm">
                <flux:select.option value="7">Últimos 7 días</flux:select.option>
                <flux:select.option value="30">Últimos 30 días</flux:select.option>
                <flux:select.option value="90">Últimos 90 días</flux:select.option>
                <flux:select.option value="365">Último año</flux:select.option>
            </flux:select>
            
            <flux:select wire:model.live="selectedCategory" label="Categoría" size="sm">
                <flux:select.option value="">Todas las categorías</flux:select.option>
                @foreach($productCategories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <flux:input 
                wire:model.live="search" 
                icon="magnifying-glass" 
                placeholder="Buscar productos..." 
                label="Buscar"
                size="sm"
            />
            
            <flux:select wire:model.live="perPage" label="Por página" size="sm">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">Todos</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <flux:icon.cube class="h-6 w-6 text-blue-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Productos Vendidos</flux:text>
                    <flux:heading size="lg" class="tabular-nums">{{ number_format($totalStats['total_products']) }}</flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.shopping-bag class="h-6 w-6 text-green-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Unidades Vendidas</flux:text>
                    <flux:heading size="lg" class="tabular-nums">{{ number_format($totalStats['total_quantity']) }}</flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <flux:icon.currency-dollar class="h-6 w-6 text-purple-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Monto Total</flux:text>
                    <flux:heading size="lg" class="tabular-nums">RD$ {{ number_format($totalStats['total_amount'], 2) }}</flux:heading>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clipboard-document-list class="h-6 w-6 text-orange-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Pedidos Totales</flux:text>
                    <flux:heading size="lg" class="tabular-nums">{{ number_format($totalStats['total_orders']) }}</flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Products Table -->
    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('code')" class="flex items-center gap-1 hover:text-blue-600">
                                Código
                                @if($sortBy === 'code')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('product')" class="flex items-center gap-1 hover:text-blue-600">
                                Producto
                                @if($sortBy === 'product')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('category')" class="flex items-center gap-1 hover:text-blue-600">
                                Categoría
                                @if($sortBy === 'category')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('total_quantity')" class="flex items-center gap-1 hover:text-blue-600 ml-auto">
                                Cantidad
                                @if($sortBy === 'total_quantity')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('total_amount')" class="flex items-center gap-1 hover:text-blue-600 ml-auto">
                                Monto Total
                                @if($sortBy === 'total_amount')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('avg_price')" class="flex items-center gap-1 hover:text-blue-600 ml-auto">
                                Precio Promedio
                                @if($sortBy === 'avg_price')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('order_count')" class="flex items-center gap-1 hover:text-blue-600 ml-auto">
                                Pedidos
                                @if($sortBy === 'order_count')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">
                            <button wire:click="sortBy('last_sale')" class="flex items-center gap-1 hover:text-blue-600">
                                Última Venta
                                @if($sortBy === 'last_sale')
                                    <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="h-4 w-4" />
                                @endif
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($paginatedData as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <flux:text class="font-mono text-sm">{{ $item['code'] }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:text class="font-medium">{{ $item['product'] }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:badge variant="soft" color="blue">{{ $item['category'] }}</flux:badge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:text class="font-medium tabular-nums">{{ number_format($item['total_quantity']) }}</flux:text>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:text class="font-medium text-green-600 tabular-nums">RD$ {{ number_format($item['total_amount'], 2) }}</flux:text>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:text class="text-gray-600 tabular-nums">RD$ {{ number_format($item['avg_price'], 2) }}</flux:text>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:text class="text-gray-600 tabular-nums">{{ number_format($item['order_count']) }}</flux:text>
                            </td>
                            <td class="py-3 px-4">
                                <flux:text class="text-sm text-gray-500">{{ $item['last_sale'] }}</flux:text>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 px-4 text-center">
                                <flux:icon.cube class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                <flux:text class="text-gray-500">No se encontraron productos vendidos en el período seleccionado</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($perPage > 0 && count($paginatedData) > 0)
            <div class="mt-6">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div>
                        @php
                            $start = (($this->page - 1) * $perPage) + 1;
                            $end = min($this->page * $perPage, count($this->productsSalesData));
                            $total = count($this->productsSalesData);
                        @endphp
                        Mostrando {{ $start }} a {{ $end }} de {{ $total }} productos
                    </div>
                    <div class="flex items-center gap-2">
                        @if($this->page > 1)
                            <flux:button size="xs" variant="ghost" wire:click="$set('page', {{ $this->page - 1 }})">
                                Anterior
                            </flux:button>
                        @endif
                        @if($end < $total)
                            <flux:button size="xs" variant="ghost" wire:click="$set('page', {{ $this->page + 1 }})">
                                Siguiente
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:card>
</div>
