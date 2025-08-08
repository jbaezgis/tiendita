<?php

use App\Models\StoreConfig;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component {
    public $config;
    
    // Form fields
    public $is_open = false;
    public $current_season = 'Sin temporada';
    public $season_start_date = '';
    public $season_end_date = '';
    public $store_opening_date = '';
    public $store_closing_date = '';
    public $max_order_amount = 10000.00;
    public $notes = '';

    public function mount()
    {
        $this->config = StoreConfig::getCurrentConfig();
        $this->loadConfig();
    }

    public function loadConfig()
    {
        $this->is_open = $this->config->is_open;
        $this->current_season = $this->config->current_season;
        $this->season_start_date = $this->config->season_start_date?->format('Y-m-d') ?? '';
        $this->season_end_date = $this->config->season_end_date?->format('Y-m-d') ?? '';
        $this->store_opening_date = $this->config->store_opening_date?->format('Y-m-d\TH:i') ?? '';
        $this->store_closing_date = $this->config->store_closing_date?->format('Y-m-d\TH:i') ?? '';
        $this->max_order_amount = $this->config->max_order_amount;
        $this->notes = $this->config->notes ?? '';
    }

    public function saveConfig()
    {
        $this->validate([
            'current_season' => 'required|string|max:255',
            'season_start_date' => 'nullable|date',
            'season_end_date' => 'nullable|date|after_or_equal:season_start_date',
            'store_opening_date' => 'nullable|date',
            'store_closing_date' => 'nullable|date|after:store_opening_date',
            'max_order_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->config->update([
            'is_open' => $this->is_open,
            'current_season' => $this->current_season,
            'season_start_date' => $this->season_start_date ?: null,
            'season_end_date' => $this->season_end_date ?: null,
            'store_opening_date' => $this->store_opening_date ?: null,
            'store_closing_date' => $this->store_closing_date ?: null,
            'max_order_amount' => $this->max_order_amount,
            'notes' => $this->notes,
        ]);

        Flux::toast(
            heading: 'Configuración guardada',
            text: 'La configuración de la tienda se ha actualizado correctamente',
            variant: 'success',
            position: 'top-right'
        );
    }

    public function toggleStoreStatus()
    {
        $this->is_open = !$this->is_open;
        $this->saveConfig();
    }

    public function getSeasonOptionsProperty()
    {
        return StoreConfig::getSeasonOptions();
    }

    public function getStoreStatusProperty()
    {
        return $this->config->getStoreStatus();
    }

    public function getSeasonStatusProperty()
    {
        return $this->config->getSeasonStatus();
    }

    public function getIsStoreOpenProperty()
    {
        return StoreConfig::isStoreOpen();
    }

    public function getStoreStatsProperty()
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $approvedOrders = Order::where('status', 'approved')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        
        return [
            'total' => $totalOrders,
            'pending' => $pendingOrders,
            'approved' => $approvedOrders,
            'delivered' => $deliveredOrders,
        ];
    }

    public function getOrdersChartDataProperty()
    {
        $days = 30;
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
}; ?>

<div>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header with Store Status -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <flux:heading size="2xl" class="text-gray-900">Configuración de la Tienda</flux:heading>
                    <flux:subheading class="text-gray-600">Gestiona el estado de la tienda y las temporadas</flux:subheading>
                </div>
                
                <!-- Store Status Badge -->
                <div class="flex items-center space-x-4 gap-4">
                    <div class="text-right">
                        <flux:text class="font-medium text-gray-700">Estado actual</flux:text>
                        <div class="flex items-center mt-1">
                            <div class="w-3 h-3 rounded-full {{ $this->isStoreOpen ? 'bg-green-500' : 'bg-red-500' }} mr-2"></div>
                            <flux:text size="sm" class="{{ $this->isStoreOpen ? 'text-green-700' : 'text-red-700' }}">
                                {{ $this->storeStatus }}
                            </flux:text>
                        </div>
                    </div>
                    
                    <flux:button 
                        wire:click="toggleStoreStatus"
                        variant="primary"
                        color="{{ $this->is_open ? 'red' : 'green' }}"
                        icon="{{ $this->is_open ? 'lock-closed' : 'lock-open' }}"
                    >
                        {{ $this->is_open ? 'Cerrar Tienda' : 'Abrir Tienda' }}
                    </flux:button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <flux:card>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <flux:icon.shopping-cart class="h-6 w-6 text-blue-600" />
                        </div>
                        <div class="flex-1">
                            <flux:text class="text-gray-500">Total Pedidos</flux:text>
                            <flux:heading size="lg" class="tabular-nums">{{ number_format($this->storeStats['total']) }}</flux:heading>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <flux:icon.clock class="h-6 w-6 text-yellow-600" />
                        </div>
                        <div class="flex-1">
                            <flux:text class="text-gray-500">Pendientes</flux:text>
                            <flux:heading size="lg" class="tabular-nums">{{ number_format($this->storeStats['pending']) }}</flux:heading>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <flux:icon.check-circle class="h-6 w-6 text-green-600" />
                        </div>
                        <div class="flex-1">
                            <flux:text class="text-gray-500">Aprobados</flux:text>
                            <flux:heading size="lg" class="tabular-nums">{{ number_format($this->storeStats['approved']) }}</flux:heading>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <flux:icon.truck class="h-6 w-6 text-purple-600" />
                        </div>
                        <div class="flex-1">
                            <flux:text class="text-gray-500">Entregados</flux:text>
                            <flux:heading size="lg" class="tabular-nums">{{ number_format($this->storeStats['delivered']) }}</flux:heading>
                        </div>
                    </div>
                </flux:card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Configuration Form -->
                <div class="lg:col-span-2">
                    <flux:card>
                        <div class="p-6">
                            <div class="mb-6">
                                <flux:heading size="lg" class="text-gray-900">Configuración General</flux:heading>
                                <flux:text class="text-gray-600">Gestiona el estado de la tienda y las temporadas</flux:text>
                            </div>
                            
                            <form wire:submit.prevent="saveConfig" class="space-y-6">
                                <!-- Season Configuration -->
                                <div class="space-y-4">
                                    <flux:heading size="lg" class="text-gray-900">Temporada Actual</flux:heading>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <flux:field>
                                                <flux:label>Temporada</flux:label>
                                                <flux:select wire:model="current_season">
                                                    @foreach($this->seasonOptions as $value => $label)
                                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </flux:field>
                                        </div>
                                        
                                        <div class="flex items-center">
                                            <flux:badge 
                                                color="{{ $this->seasonStatus === 'Temporada activa' ? 'green' : ($this->seasonStatus === 'Temporada próxima' ? 'yellow' : 'zinc') }}"
                                                size="lg"
                                            >
                                                {{ $this->seasonStatus }}
                                            </flux:badge>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <flux:field>
                                            <flux:label>Fecha de Inicio</flux:label>
                                            <flux:input type="date" wire:model="season_start_date" />
                                        </flux:field>
                                        
                                        <flux:field>
                                            <flux:label>Fecha de Fin</flux:label>
                                            <flux:input type="date" wire:model="season_end_date" />
                                        </flux:field>
                                    </div>
                                </div>

                                <!-- Store Schedule -->
                                <div class="space-y-4">
                                    <flux:heading size="lg" class="text-gray-900">Horario de la Tienda</flux:heading>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <flux:field>
                                            <flux:label>Fecha y Hora de Apertura</flux:label>
                                            <flux:input type="datetime-local" wire:model="store_opening_date" />
                                            <flux:description>Dejar vacío para apertura inmediata</flux:description>
                                        </flux:field>
                                        
                                        <flux:field>
                                            <flux:label>Fecha y Hora de Cierre</flux:label>
                                            <flux:input type="datetime-local" wire:model="store_closing_date" />
                                            <flux:description>Dejar vacío para cierre manual</flux:description>
                                        </flux:field>
                                    </div>
                                </div>

                                <!-- Order Limits -->
                                <div class="space-y-4">
                                    <flux:heading size="lg" class="text-gray-900">Límites de Pedidos</flux:heading>
                                    
                                    <flux:field>
                                        <flux:label>Monto Máximo por Pedido (RD$)</flux:label>
                                        <flux:input type="number" step="0.01" wire:model="max_order_amount" />
                                    </flux:field>
                                </div>

                                <!-- Notes -->
                                <div class="space-y-4">
                                    <flux:heading size="lg" class="text-gray-900">Notas Adicionales</flux:heading>
                                    
                                    <flux:field>
                                        <flux:label>Notas</flux:label>
                                        <flux:textarea wire:model="notes" rows="3" placeholder="Información adicional sobre la temporada o configuración..." />
                                    </flux:field>
                                </div>

                                <div class="flex justify-end">
                                    <flux:button type="submit" variant="primary" icon="check">
                                        Guardar Configuración
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    </flux:card>
                </div>

                <!-- Sidebar with Charts and Info -->
                <div class="space-y-6">
                    <!-- Orders Chart -->
                    <flux:card>
                        <div class="p-6">
                            <div class="mb-4">
                                <flux:heading size="lg" class="text-gray-900">Actividad de Pedidos</flux:heading>
                                <flux:text class="text-gray-600">Últimos 30 días</flux:text>
                            </div>
                            
                            <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <flux:icon.chart-bar class="h-12 w-12 text-gray-400 mx-auto mb-2" />
                                    <flux:text class="text-gray-500">Gráfico de actividad</flux:text>
                                    <flux:text size="sm" class="text-gray-400">Componente Chart Pro requerido</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:card>

                    <!-- Quick Actions -->
                    <flux:card>
                        <div class="p-6">
                            <div class="mb-4">
                                <flux:heading size="lg" class="text-gray-900">Acciones Rápidas</flux:heading>
                            </div>
                            
                            <div class="space-y-3">
                                <flux:button variant="ghost" icon="eye" href="{{ route('orders.index') }}" class="w-full justify-start">
                                    Ver Todos los Pedidos
                                </flux:button>
                                
                                <flux:button variant="ghost" icon="users" href="{{ route('employees.index') }}" class="w-full justify-start">
                                    Gestionar Empleados
                                </flux:button>
                                
                                <flux:button variant="ghost" icon="cube" href="{{ route('products.index') }}" class="w-full justify-start">
                                    Gestionar Productos
                                </flux:button>
                                
                                <flux:button variant="ghost" icon="chart-bar" href="{{ route('dashboard') }}" class="w-full justify-start">
                                    Ver Dashboard
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>

                    <!-- Information Panel -->
                    <flux:callout variant="secondary" icon="information-circle">
                        <flux:callout.heading>Información Importante</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="list-disc pl-5 space-y-1 text-sm">
                                <li>Cuando la tienda está cerrada, los empleados no pueden crear nuevos pedidos</li>
                                <li>Los pedidos existentes permanecen visibles y se pueden procesar normalmente</li>
                                <li>La configuración de temporada ayuda a organizar los productos por época del año</li>
                                <li>El monto máximo por pedido se aplica a todos los empleados</li>
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                </div>
            </div>
        </div>
    </div>
</div> 