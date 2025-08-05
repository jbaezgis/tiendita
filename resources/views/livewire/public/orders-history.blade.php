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

        $this->selectedOrder = $order->load(['items.product', 'approver']);
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
            text: 'Tu pedido ha sido cancelado exitosamente',
            variant: 'success',
            position: 'top-right'
        );
    }

    public function getOrdersProperty()
    {
        $query = Order::with(['items.product', 'approver'])
            ->where('employee_id', $this->employee->id);
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('order_number', 'like', "%{$this->search}%");
        });

        $query->when($this->statusFilter, function ($query) {
            $query->where('status', $this->statusFilter);
        });
        
        return $query->paginate($this->perPage);
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
              
                
                <flux:button variant="outline" href="{{ route('public.orders') }}" icon="arrow-left">
                    Volver a la Tienda
                </flux:button>
                
                <!-- Logout -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button type="submit" icon:trailing="log-out">
                        Salir
                    </flux:button>
                </form>
                
            </div>
        </div>
    </div>
    

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <flux:heading size="2xl" class="text-gray-900">¡Bienvenido, {{ $this->employee->name }}!</flux:heading>
            <flux:subheading class="text-gray-600">Historial y estado de tus pedidos</flux:subheading>
        </div>

        <!-- Filters -->
        <flux:card class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Filtros</flux:heading>
                <flux:text size="sm" class="text-gray-500">{{ $this->orders->total() }} pedido(s)</flux:text>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input 
                    wire:model.live="search" 
                    icon="magnifying-glass" 
                    placeholder="Buscar por número de pedido..." 
                    label="Buscar"
                />
                <flux:select wire:model.live="statusFilter" placeholder="Estado" label="Filtrar por estado">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    @foreach($statusOptions as $key => $status)
                        <flux:select.option value="{{ $key }}">{{ $status }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        <!-- Orders Table -->
        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'order_number'" :direction="$sortDirection" wire:click="sort('order_number')">
                    Número
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'order_date'" :direction="$sortDirection" wire:click="sort('order_date')">
                    Fecha
                </flux:table.column>
                <flux:table.column>Items</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'total'" :direction="$sortDirection" wire:click="sort('total')">
                    Total
                </flux:table.column>
                <flux:table.column>Prioridad</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column>Acciones</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->orders as $order)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:badge variant="pill" color="blue" size="sm">{{ $order->order_number }}</flux:badge>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div>
                                <flux:text>{{ $order->order_date->format('d/m/Y') }}</flux:text>
                                <flux:text size="sm" class="text-gray-500 block">{{ $order->created_at->format('H:i') }}</flux:text>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.cube class="w-4 h-4 text-gray-600" />
                                <span>{{ $order->items->count() }} item(s)</span>
                                <flux:text size="sm" class="text-gray-500">({{ $order->getTotalQuantity() }} unidades)</flux:text>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.currency-dollar class="w-4 h-4 text-green-600" />
                                <span class="font-medium">${{ number_format($order->total, 2) }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$order->getPriorityColor()" size="sm">
                                {{ $order::getPriorityOptions()[$order->priority] }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$order->getStatusColor()" size="sm">
                                {{ $order::getStatusOptions()[$order->status] }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" wire:click="viewOrder({{ $order->id }})">
                                        Ver Detalles
                                    </flux:menu.item>
                                    
                                    @if($order->status === 'pending')
                                        <flux:menu.separator />
                                        <flux:menu.item icon="x-circle" variant="danger" wire:click="cancelOrder({{ $order->id }})">
                                            Cancelar Pedido
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-8">
                            <div class="flex flex-col items-center">
                                <flux:icon.clipboard-document-list class="h-12 w-12 text-gray-400 mb-4" />
                                <flux:text class="text-gray-500">No tienes pedidos</flux:text>
                                <flux:text size="sm" class="text-gray-400">Crea tu primer pedido en la tienda</flux:text>
                                <flux:button variant="primary" size="sm" href="{{ route('public.orders') }}" class="mt-4">
                                    Ir a la Tienda
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Order Details Modal -->
    <flux:modal name="order-modal" :open="$showOrderModal" wire:model="showOrderModal" class="max-w-4xl">
        @if($selectedOrder)
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <flux:icon.clipboard-document-list class="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <flux:heading size="lg">Pedido {{ $selectedOrder->order_number }}</flux:heading>
                            <flux:subheading>{{ $selectedOrder->order_date->format('d/m/Y H:i') }}</flux:subheading>
                        </div>
                    </div>
                    <flux:badge :color="$selectedOrder->getStatusColor()" size="lg">
                        {{ $selectedOrder::getStatusOptions()[$selectedOrder->status] }}
                    </flux:badge>
                </div>

                <!-- Order Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Total</flux:text>
                        <flux:text class="font-bold text-lg">${{ number_format($selectedOrder->total, 2) }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-600">Prioridad</flux:text>
                        <flux:badge :color="$selectedOrder->getPriorityColor()" size="sm">
                            {{ $selectedOrder::getPriorityOptions()[$selectedOrder->priority] }}
                        </flux:badge>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-600">Items</flux:text>
                        <flux:text class="font-medium">{{ $selectedOrder->items->count() }} productos</flux:text>
                    </div>
                    @if($selectedOrder->approver)
                        <div>
                            <flux:text size="sm" class="text-gray-600">Aprobado por</flux:text>
                            <flux:text class="font-medium">{{ $selectedOrder->approver->name }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Order Items -->
                <div>
                    <flux:heading size="lg" class="mb-4">Productos del Pedido</flux:heading>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Unit.</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($selectedOrder->items as $item)
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div>
                                                <flux:text class="font-medium">{{ $item->product->description }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $item->product->code }}</flux:text>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <flux:text>{{ $item->quantity }}</flux:text>
                                        </td>
                                        <td class="px-4 py-4">
                                            <flux:text>${{ number_format($item->price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-4">
                                            <flux:text class="font-medium">${{ number_format($item->subtotal, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-4">
                                            <flux:badge :color="$item->getStatusColor()" size="sm">
                                                {{ ucfirst($item->status) }}
                                            </flux:badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right font-medium">Total:</td>
                                    <td class="px-4 py-3 font-bold text-lg">${{ number_format($selectedOrder->total, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                @if($selectedOrder->notes)
                    <div>
                        <flux:heading size="lg" class="mb-2">Notas</flux:heading>
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <flux:text>{{ $selectedOrder->notes }}</flux:text>
                        </div>
                    </div>
                @endif

                <!-- Rejection Reason -->
                @if($selectedOrder->status === 'rejected' && $selectedOrder->rejection_reason)
                    <div>
                        <flux:heading size="lg" class="mb-2">Razón del Rechazo</flux:heading>
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <flux:text>{{ $selectedOrder->rejection_reason }}</flux:text>
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeOrderModal">
                        Cerrar
                    </flux:button>
                    @if($selectedOrder->status === 'pending')
                        <flux:button variant="danger" wire:click="cancelOrder({{ $selectedOrder->id }})">
                            Cancelar Pedido
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>