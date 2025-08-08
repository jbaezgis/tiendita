<?php

use App\Models\Order;
use App\Models\Employee;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.public')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public $search = '';
    public $statusFilter = '';
    public $perPage = 10;
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    
    // Modal states
    public $showOrderModal = false;
    public $selectedOrder = null;
    
    // Employee data
    public $employee = null;

    public function mount()
    {
        // Get current authenticated user's employee record
        $this->employee = auth()->user()->employee;
        
        if (!$this->employee) {
            return redirect()->route('login')->with('error', 'No tienes permisos para acceder a esta página');
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function sort($column) 
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function viewOrder(Order $order)
    {
        // Ensure the order belongs to the current employee
        if ($order->employee_id !== $this->employee->id) {
            Flux::toast(
                heading: 'Error',
                text: 'No tienes permisos para ver este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->selectedOrder = $order->load(['items.product', 'approver', 'rejector']);
        $this->showOrderModal = true;
    }

    public function closeOrderModal()
    {
        $this->showOrderModal = false;
        $this->selectedOrder = null;
    }

    public function cancelOrder(Order $order)
    {
        // Ensure the order belongs to the current employee and can be cancelled
        if ($order->employee_id !== $this->employee->id || !in_array($order->status, ['pending'])) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede cancelar este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $order->cancel();

        Flux::toast(
            heading: 'Pedido cancelado',
            text: "El pedido #{$order->order_number} ha sido cancelado exitosamente",
            variant: 'success',
            position: 'top-right'
        );
    }

    public function getOrdersProperty()
    {
        $query = Order::where('employee_id', $this->employee->id)
            ->with(['items.product', 'approver', 'rejector']);

        if ($this->search) {
            $query->where('order_number', 'like', "%{$this->search}%");
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy($this->sortBy, $this->sortDirection)->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'statusOptions' => Order::getStatusOptions(),
        ];
    }
}; ?>

<div>
    {{-- header --}}
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-center py-2">
        <div class="text-2xl font-bold">Tiendita AJFA</div>
        <div class="">Tienda de productos de Grupo AJFA</div>
    </div>
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <flux:button variant="primary" color="blue" href="{{ route('public.orders') }}" icon="shopping-cart" class="flex items-center gap-2">
                    Ir a la tienda
                </flux:button>
                <div class="flex items-center gap-2">
                    {{-- <flux:button variant="primary" color="blue" href="{{ route('public.orders') }}" icon="plus">
                        Nuevo Pedido
                    </flux:button> --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" icon:trailing="log-out">
                            Salir
                        </flux:button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <flux:heading size="2xl" class="text-gray-900">¡Bienvenido, {{ $this->employee->name }}!</flux:heading>
            <flux:subheading class="text-gray-600">Historial y estado de tus pedidos</flux:subheading>
        </div>

        <!-- Stats Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <flux:card class="p-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <flux:icon.clipboard-document-list class="h-5 w-5 text-blue-600" />
                    </div>
                    <div>
                        <flux:text class="font-bold text-xl text-blue-600">{{ $this->orders->total() }}</flux:text>
                        <flux:text size="sm" class="text-gray-600">Total Pedidos</flux:text>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <flux:icon.clock class="h-5 w-5 text-yellow-600" />
                    </div>
                    <div>
                        <flux:text class="font-bold text-xl text-yellow-600">{{ $this->orders->where('status', 'pending')->count() }}</flux:text>
                        <flux:text size="sm" class="text-gray-600">Pendientes</flux:text>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                    </div>
                    <div>
                        <flux:text class="font-bold text-xl text-green-600">{{ $this->orders->where('status', 'approved')->count() }}</flux:text>
                        <flux:text size="sm" class="text-gray-600">Aprobados</flux:text>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <flux:icon.truck class="h-5 w-5 text-purple-600" />
                    </div>
                    <div>
                        <flux:text class="font-bold text-xl text-purple-600">{{ $this->orders->where('status', 'delivered')->count() }}</flux:text>
                        <flux:text size="sm" class="text-gray-600">Entregados</flux:text>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Filters -->
        <flux:card class="mb-6">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Filtros</flux:heading>
                    <flux:text size="sm" class="text-gray-500">{{ $this->orders->total() }} pedido(s)</flux:text>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Buscar</flux:label>
                        <flux:input 
                            wire:model.live="search" 
                            icon="magnifying-glass" 
                            placeholder="Buscar por número de pedido..." 
                        />
                    </flux:field>
                    <flux:field>
                        <flux:label>Estado</flux:label>
                        <flux:select wire:model.live="statusFilter" placeholder="Filtrar por estado">
                            <flux:select.option value="">Todos los estados</flux:select.option>
                            @foreach($statusOptions as $key => $status)
                                <flux:select.option value="{{ $key }}">{{ $status }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
            </div>
        </flux:card>

        <!-- Orders Cards -->
        <div class="space-y-4">
            @forelse ($this->orders as $order)
                <flux:card class="hover:shadow-lg transition-shadow duration-200 p-0">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <flux:badge variant="pill" color="blue" size="sm">
                                {{ $order->order_number }}
                            </flux:badge>
                            <flux:badge :color="$order->getStatusColor()" size="sm">
                                {{ $order::getStatusOptions()[$order->status] }}
                            </flux:badge>

                        </div>
                        
                        <flux:text class="text-gray-600">
                            {{ $order->order_date->format('d/m/Y') }} a las {{ $order->created_at->format('H:i') }}
                        </flux:text>
                       

                        <!-- Items Summary -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <flux:text class="font-medium">Productos</flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    {{ $order->items->count() }} item(s) - {{ $order->getTotalQuantity() }} unidades
                                </flux:text>
                            </div>
                            <div class="space-y-2">
                                @foreach($order->items->take(3) as $item)
                                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-2">
                                        <flux:text size="sm" class="text-gray-700">
                                            {{ $item->product->description }}
                                        </flux:text>
                                        <flux:text size="sm" class="text-gray-500">
                                            {{ $item->quantity }} x ${{ number_format($item->price, 2) }}
                                        </flux:text>
                                    </div>
                                @endforeach
                                @if($order->items->count() > 3)
                                    <flux:text size="sm" class="text-gray-500 text-center">
                                        +{{ $order->items->count() - 3 }} productos más
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <flux:text size="lg" class="">
                                Monto total:
                            </flux:text>
                            <flux:text size="lg" class="text-green-600 font-bold">
                                ${{ number_format($order->total, 2) }}
                            </flux:text>

                        </div>
                        <flux:separator class="my-4"/>
                        <!-- Status Timeline -->
                        <div class="mb-4">
                            <flux:text size="sm" class="font-medium mb-2">Progreso del Pedido</flux:text>
                            <div class="flex items-center space-x-2">
                                <div class="flex-1">
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        @php
                                            $progress = 0;
                                            switch($order->status) {
                                                case 'pending': $progress = 25; break;
                                                case 'approved': $progress = 50; break;
                                                case 'in_progress': $progress = 75; break;
                                                case 'delivered': $progress = 100; break;
                                                case 'cancelled': $progress = 0; break;
                                            }
                                        @endphp
                                        <div class="h-full bg-blue-500 rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                                <flux:text size="sm" class="text-gray-500">{{ $progress }}%</flux:text>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            {{-- <div class="flex items-center gap-2">
                            </div> --}}
                            <flux:button 
                                wire:click="viewOrder({{ $order->id }})"
                                variant="ghost" 
                                size="sm"
                                icon="eye"
                            >
                                Ver Detalles
                            </flux:button>
                            
                            @if($order->status === 'pending')
                                <flux:button 
                                    wire:click="cancelOrder({{ $order->id }})"
                                    variant="ghost" 
                                    size="sm"
                                    color="red"
                                    icon="x-mark"
                                >
                                    Cancelar
                                </flux:button>
                            @endif
                            
                        </div>
                        <div class="flex items-center justify-center gap-2 mt-4">
                            @if($order->notes)
                                <flux:icon.chat-bubble-left class="w-4 h-4 text-gray-400" />
                            @endif
                            @if($order->approved_by)
                                <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                            @endif
                            @if($order->rejected_by)
                                <flux:icon.x-circle class="w-4 h-4 text-red-500" />
                            @endif
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <div class="p-8 text-center">
                        <flux:icon.clipboard-document-list class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <flux:heading size="lg" class="text-gray-900 mb-2">No hay pedidos</flux:heading>
                        <flux:text class="text-gray-600 mb-6">
                            Aún no has realizado ningún pedido. ¡Haz tu primer pedido ahora!
                        </flux:text>
                        <flux:button href="{{ route('public.orders') }}" variant="primary" icon="plus">
                            Hacer Primer Pedido
                        </flux:button>
                    </div>
                </flux:card>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($this->orders->hasPages())
            <div class="mt-6">
                {{ $this->orders->links() }}
            </div>
        @endif
    </div>

    <!-- Order Details Modal -->
    @if($selectedOrder)
        <flux:modal name="order-details-modal" wire:model="showOrderModal">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Detalles del Pedido #{{ $selectedOrder->order_number }}</flux:heading>
                    <flux:button variant="ghost" icon="x-mark" wire:click="closeOrderModal" />
                </div>

                <!-- Order Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:card>
                        <div class="p-4">
                            <flux:heading size="md" class="mb-3">Información del Pedido</flux:heading>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <flux:text class="text-gray-600">Estado:</flux:text>
                                    <flux:badge :color="$selectedOrder->getStatusColor()" size="sm">
                                        {{ $selectedOrder::getStatusOptions()[$selectedOrder->status] }}
                                    </flux:badge>
                                </div>
                                <div class="flex justify-between">
                                    <flux:text class="text-gray-600">Prioridad:</flux:text>
                                    <flux:badge :color="$selectedOrder->getPriorityColor()" size="sm">
                                        {{ $selectedOrder::getPriorityOptions()[$selectedOrder->priority] }}
                                    </flux:badge>
                                </div>
                                <div class="flex justify-between">
                                    <flux:text class="text-gray-600">Fecha:</flux:text>
                                    <flux:text>{{ $selectedOrder->order_date->format('d/m/Y') }}</flux:text>
                                </div>
                                <div class="flex justify-between">
                                    <flux:text class="text-gray-600">Total:</flux:text>
                                    <flux:text class="font-medium">${{ number_format($selectedOrder->total, 2) }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:card>

                    <flux:card>
                        <div class="p-4">
                            <flux:heading size="md" class="mb-3">Productos</flux:heading>
                            <div class="space-y-3">
                                @foreach($selectedOrder->items as $item)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex-1">
                                            <flux:text class="font-medium">{{ $item->product->description }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500">
                                                {{ $item->quantity }} unidades
                                            </flux:text>
                                        </div>
                                        <flux:text class="font-medium">${{ number_format($item->subtotal, 2) }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </flux:card>
                </div>

                @if($selectedOrder->notes)
                    <flux:card>
                        <div class="p-4">
                            <flux:heading size="md" class="mb-3">Notas</flux:heading>
                            <flux:text>{{ $selectedOrder->notes }}</flux:text>
                        </div>
                    </flux:card>
                @endif

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="closeOrderModal">
                        Cerrar
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>