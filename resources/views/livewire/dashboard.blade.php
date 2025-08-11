<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Category;
use App\Models\StoreConfig;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component {
    public $selectedPeriod = '30';
    public $selectedChart = 'orders';

    public function mount()
    {
        // Default to last 30 days
    }

    public function getOrdersDataProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $orders = Order::whereBetween('created_at', [$startDate, now()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $orderData = $orders->where('date', $date->format('Y-m-d'))->first();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'orders' => $orderData ? $orderData->count : 0,
                'amount' => $orderData ? (float) $orderData->total_amount : 0,
            ];
        }

        return $data;
    }

    public function getStatusDataProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $orders = Order::whereBetween('created_at', [$startDate, now()])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $statusOptions = Order::getStatusOptions();
        $data = [];
        
        foreach ($statusOptions as $status => $label) {
            $count = $orders->where('status', $status)->first()?->count ?? 0;
            $data[] = [
                'status' => $label,
                'count' => $count,
                'color' => $this->getStatusColor($status),
            ];
        }

        return $data;
    }

    public function getPriorityDataProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $orders = Order::whereBetween('created_at', [$startDate, now()])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get();

        $priorityOptions = Order::getPriorityOptions();
        $data = [];
        
        foreach ($priorityOptions as $priority => $label) {
            $count = $orders->where('priority', $priority)->first()?->count ?? 0;
            $data[] = [
                'priority' => $label,
                'count' => $count,
                'color' => $this->getPriorityColor($priority),
            ];
        }

        return $data;
    }

    public function getTopProductsDataProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $products = OrderItem::whereHas('order', function ($query) use ($startDate) {
            $query->whereBetween('created_at', [$startDate, now()]);
        })
        ->with('product')
        ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(subtotal) as total_amount')
        ->groupBy('product_id')
        ->orderByDesc('total_quantity')
        ->limit(10)
        ->get();

        $data = [];
        foreach ($products as $item) {
            $data[] = [
                'product' => $item->product->description,
                'quantity' => $item->total_quantity,
                'amount' => (float) $item->total_amount,
            ];
        }

        return $data;
    }

    public function getTopEmployeesDataProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $employees = Order::whereBetween('created_at', [$startDate, now()])
            ->with('employee')
            ->selectRaw('employee_id, COUNT(*) as order_count, SUM(total) as total_amount')
            ->groupBy('employee_id')
            ->orderByDesc('order_count')
            ->limit(10)
            ->get();

        $data = [];
        foreach ($employees as $item) {
            $data[] = [
                'employee' => $item->employee->name,
                'orders' => $item->order_count,
                'amount' => (float) $item->total_amount,
            ];
        }

        return $data;
    }

    public function getStatsProperty()
    {
        $days = (int) $this->selectedPeriod;
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        $totalOrders = Order::whereBetween('created_at', [$startDate, now()])->count();
        $pendingOrders = Order::whereBetween('created_at', [$startDate, now()])->where('status', 'pending')->count();
        $totalAmount = Order::whereBetween('created_at', [$startDate, now()])->sum('total');
        $avgOrderValue = $totalOrders > 0 ? $totalAmount / $totalOrders : 0;
        
        $previousStartDate = $startDate->copy()->subDays($days);
        $previousEndDate = $startDate->copy()->subDay();
        $previousOrders = Order::whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();
        $previousAmount = Order::whereBetween('created_at', [$previousStartDate, $previousEndDate])->sum('total');
        
        $orderGrowth = $previousOrders > 0 ? (($totalOrders - $previousOrders) / $previousOrders) * 100 : 0;
        $amountGrowth = $previousAmount > 0 ? (($totalAmount - $previousAmount) / $previousAmount) * 100 : 0;

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'total_amount' => $totalAmount,
            'avg_order_value' => $avgOrderValue,
            'order_growth' => $orderGrowth,
            'amount_growth' => $amountGrowth,
        ];
    }

    private function getStatusColor($status)
    {
        return match($status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'delivered' => 'blue',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    private function getPriorityColor($priority)
    {
        return match($priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    public function with(): array
    {
        return [
            'stats' => $this->stats,
            'ordersData' => $this->ordersData,
            'statusData' => $this->statusData,
            'priorityData' => $this->priorityData,
            'topProductsData' => $this->topProductsData,
            'topEmployeesData' => $this->topEmployeesData,
        ];
    }

    public function getStoreStatusProperty()
    {
        $config = StoreConfig::getCurrentConfig();
        return [
            'is_open' => StoreConfig::isStoreOpen(),
            'status' => $config->getStoreStatus(),
            'season' => $config->current_season,
            'season_status' => $config->getSeasonStatus(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Dashboard de Pedidos</flux:heading>
            <flux:subheading>Resumen y estadísticas del sistema de pedidos</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="selectedPeriod" size="sm">
                <flux:select.option value="7">Últimos 7 días</flux:select.option>
                <flux:select.option value="30">Últimos 30 días</flux:select.option>
                <flux:select.option value="90">Últimos 90 días</flux:select.option>
            </flux:select>
            <flux:button icon="store" variant="primary" color="blue" href="{{ route('public.orders') }}" size="sm">Ir a la Tienda</flux:button>
        </div>
    </div>

    <!-- Store Status Callout -->
    @if(!$this->storeStatus['is_open'])
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>Tienda Cerrada</flux:callout.heading>
            <flux:callout.text>
                La tienda está cerrada actualmente. Los empleados no pueden crear nuevos pedidos.
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button href="{{ route('store-config.index') }}" size="sm">
                    Configurar Tienda
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Tienda Abierta</flux:callout.heading>
            <flux:callout.text>
                {{ $this->storeStatus['status'] }} - Temporada: {{ $this->storeStatus['season'] }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button variant="ghost" href="{{ route('store-config.index') }}" size="sm">
                    Ver Configuración
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <!-- Employee Link Warning -->
    @if(auth()->user()->hasRole(['empleado', 'supervisor']) && !auth()->user()->employee)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Cuenta no vinculada a empleado</flux:callout.heading>
            <flux:callout.text>
                Tu cuenta tiene roles de empleado pero no está vinculada a un registro de empleado. 
                Esto puede limitar algunas funcionalidades. Contacta al administrador para resolver esto.
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button href="{{ route('employees.index') }}" size="sm">
                    Ver Empleados
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Orders -->
        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clipboard-document-list class="h-6 w-6 text-blue-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Total Pedidos</flux:text>
                    <flux:heading size="lg" class="tabular-nums">{{ number_format($stats['total_orders']) }}</flux:heading>
                    <flux:text size="sm" class="{{ $stats['order_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $stats['order_growth'] >= 0 ? '+' : '' }}{{ number_format($stats['order_growth'], 1) }}% vs período anterior
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Pending Orders -->
        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clock class="h-6 w-6 text-yellow-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Pendientes</flux:text>
                    <flux:heading size="lg" class="tabular-nums">{{ number_format($stats['pending_orders']) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">
                        {{ $stats['total_orders'] > 0 ? number_format(($stats['pending_orders'] / $stats['total_orders']) * 100, 1) : 0 }}% del total
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Total Amount -->
        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.currency-dollar class="h-6 w-6 text-green-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Monto Total</flux:text>
                    <flux:heading size="lg" class="tabular-nums">${{ number_format($stats['total_amount'], 2) }}</flux:heading>
                    <flux:text size="sm" class="{{ $stats['amount_growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $stats['amount_growth'] >= 0 ? '+' : '' }}{{ number_format($stats['amount_growth'], 1) }}% vs período anterior
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Average Order Value -->
        <flux:card>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <flux:icon.chart-bar class="h-6 w-6 text-purple-600" />
                </div>
                <div class="flex-1">
                    <flux:text class="text-gray-500">Valor Promedio</flux:text>
                    <flux:heading size="lg" class="tabular-nums">${{ number_format($stats['avg_order_value'], 2) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">por pedido</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Orders Trend Chart -->
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="lg">Tendencia de Pedidos</flux:heading>
                    <flux:subheading>Evolución de pedidos y montos en el tiempo</flux:subheading>
                </div>
            </div>
            
            <flux:chart wire:model="ordersData" class="aspect-[3/1]">
                <flux:chart.svg>
                    <flux:chart.line field="orders" class="text-blue-500 dark:text-blue-400" />
                    <flux:chart.point field="orders" class="text-blue-500" r="4" />
                    <flux:chart.axis axis="x" field="date">
                        <flux:chart.axis.tick />
                        <flux:chart.axis.line />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                    <flux:chart.cursor />
                </flux:chart.svg>
                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" :format="['month' => 'short', 'day' => 'numeric']" />
                    <flux:chart.tooltip.value field="orders" label="Pedidos" />
                    <flux:chart.tooltip.value field="amount" label="Monto" :format="['style' => 'currency', 'currency' => 'USD']" />
                </flux:chart.tooltip>
            </flux:chart>
        </flux:card>

        <!-- Status Distribution Chart -->
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="lg">Distribución por Estado</flux:heading>
                    <flux:subheading>Proporción de pedidos por estado</flux:subheading>
                </div>
            </div>
            
            <div class="space-y-4">
                @foreach($statusData as $item)
                    @if($item['count'] > 0)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full bg-{{ $item['color'] }}-500"></div>
                                <flux:text>{{ $item['status'] }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ number_format($item['count']) }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    ({{ $stats['total_orders'] > 0 ? number_format(($item['count'] / $stats['total_orders']) * 100, 1) : 0 }}%)
                                </flux:text>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </flux:card>
    </div>

    <!-- Tables Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Products -->
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="lg">Productos Más Solicitados</flux:heading>
                    <flux:subheading>Productos con mayor cantidad solicitada</flux:subheading>
                </div>
            </div>
            
            <div class="space-y-4">
                @foreach($topProductsData as $index => $item)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <flux:text size="xs" class="font-bold text-blue-600">{{ $index + 1 }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $item['product'] }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ number_format($item['quantity']) }} unidades</flux:text>
                            </div>
                        </div>
                        <flux:text class="font-medium">${{ number_format($item['amount'], 2) }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <!-- Top Employees -->
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="lg">Empleados Más Activos</flux:heading>
                    <flux:subheading>Empleados con más pedidos realizados</flux:subheading>
                </div>
            </div>
            
            <div class="space-y-4">
                @foreach($topEmployeesData as $index => $item)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <flux:text size="xs" class="font-bold text-green-600">{{ $index + 1 }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $item['employee'] }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ number_format($item['orders']) }} pedidos</flux:text>
                            </div>
                        </div>
                        <flux:text class="font-medium">${{ number_format($item['amount'], 2) }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <!-- Priority Distribution -->
    <flux:card>
        <div class="flex items-center justify-between mb-6">
            <div>
                <flux:heading size="lg">Distribución por Prioridad</flux:heading>
                <flux:subheading>Proporción de pedidos por nivel de prioridad</flux:subheading>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($priorityData as $item)
                @if($item['count'] > 0)
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <div class="w-4 h-4 rounded-full bg-{{ $item['color'] }}-500 mx-auto mb-2"></div>
                        <flux:text class="font-medium">{{ $item['priority'] }}</flux:text>
                        <flux:heading size="lg" class="text-{{ $item['color'] }}-600">{{ number_format($item['count']) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">
                            {{ $stats['total_orders'] > 0 ? number_format(($item['count'] / $stats['total_orders']) * 100, 1) : 0 }}%
                        </flux:text>
                    </div>
                @endif
            @endforeach
        </div>
    </flux:card>
</div> 