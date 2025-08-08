<?php

use App\Models\Order;
use App\Models\Employee;
use App\Models\Category;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrdersExport;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public $search = '';
    public $statusFilter = '';
    public $priorityFilter = '';
    public $employeeFilter = '';
    public $perPage = 10;
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    
    // Modal states
    public $showApprovalModal = false;
    public $showRejectionModal = false;
    public $selectedOrder = null;
    public $rejectionReason = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter()
    {
        $this->resetPage();
    }

    public function updatedEmployeeFilter()
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

    public function openApprovalModal(Order $order)
    {
        $this->selectedOrder = $order;
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
        $this->selectedOrder = null;
    }

    public function openRejectionModal(Order $order)
    {
        $this->selectedOrder = $order;
        $this->rejectionReason = '';
        $this->showRejectionModal = true;
    }

    public function closeRejectionModal()
    {
        $this->showRejectionModal = false;
        $this->selectedOrder = null;
        $this->rejectionReason = '';
    }

    public function approveOrder()
    {
        if (!$this->selectedOrder || !$this->selectedOrder->canBeApproved()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede aprobar este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->selectedOrder->approve(auth()->user());

        Flux::toast(
            heading: 'Pedido aprobado',
            text: 'El pedido ha sido aprobado exitosamente',
            variant: 'success',
            position: 'top-right'
        );

        $this->closeApprovalModal();
    }

    public function rejectOrder()
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:10',
        ], [
            'rejectionReason.required' => 'La razón del rechazo es obligatoria',
            'rejectionReason.min' => 'La razón debe tener al menos 10 caracteres',
        ]);

        if (!$this->selectedOrder || !$this->selectedOrder->canBeRejected()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede rechazar este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->selectedOrder->reject(auth()->user(), $this->rejectionReason);

        Flux::toast(
            heading: 'Pedido rechazado',
            text: 'El pedido ha sido rechazado',
            variant: 'success',
            position: 'top-right'
        );

        $this->closeRejectionModal();
    }

    public function deliverOrder(Order $order)
    {
        if (!$order->canBeDelivered()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede marcar como entregado este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $order->deliver();

        Flux::toast(
            heading: 'Pedido entregado',
            text: 'El pedido ha sido marcado como entregado',
            variant: 'success',
            position: 'top-right'
        );
    }

    public function export()
    {
        return Excel::download(
            new OrdersExport(
                $this->search,
                $this->statusFilter,
                $this->priorityFilter,
                $this->employeeFilter,
                $this->sortBy,
                $this->sortDirection
            ),
            'pedidos-' . now()->format('d-m-Y h:i a') . '.xlsx'
        );
    }

    public function getOrdersProperty()
    {
        $query = Order::with(['employee', 'category', 'items.product', 'approver', 'rejector']);
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('employee', function ($q) {
                    $q->where('name', 'like', "%{$this->search}%");
                });
        });

        $query->when($this->statusFilter, function ($query) {
            $query->where('status', $this->statusFilter);
        });

        $query->when($this->priorityFilter, function ($query) {
            $query->where('priority', $this->priorityFilter);
        });

        $query->when($this->employeeFilter, function ($query) {
            $query->where('employee_id', $this->employeeFilter);
        });
        
        return $query->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'employees' => Employee::where('active', true)->orderBy('name')->get(),
            'statusOptions' => Order::getStatusOptions(),
            'priorityOptions' => Order::getPriorityOptions(),
            'employeesWithPendingOrders' => Employee::where('active', true)
                ->whereHas('orders', function ($query) {
                    $query->where('status', 'pending');
                })
                ->with(['orders' => function ($query) {
                    $query->where('status', 'pending')->latest();
                }])
                ->orderBy('name')
                ->get(),
            'employeesWithApprovedOrders' => Employee::where('active', true)
                ->whereHas('orders', function ($query) {
                    $query->where('status', 'approved');
                })
                ->with(['orders' => function ($query) {
                    $query->where('status', 'approved')->latest();
                }])
                ->orderBy('name')
                ->get(),
            'employeesAvailableForOrders' => Employee::where('active', true)
                ->whereDoesntHave('orders', function ($query) {
                    $query->whereIn('status', ['pending', 'approved']);
                })
                ->count(),
        ];
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">{{ __('app.Order Management') }}</flux:heading>
            <flux:subheading>{{ __('app.Employee order administration and approval') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" href="{{ route('orders.create') }}" variant="primary" size="sm">{{ __('app.Create Order') }}</flux:button>
            <flux:separator vertical />
            <flux:button wire:click="export" icon="document-arrow-down" variant="outline" size="sm">{{ __('app.Export Excel') }}</flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-6"/>

    <!-- Employee Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Available Employees -->
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.user-plus class="h-6 w-6 text-green-600" />
                </div>
                <div>
                    <flux:text class="font-bold text-2xl text-green-600">{{ $employeesAvailableForOrders }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">{{ __('app.Available employees') }}</flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Employees with Pending Orders -->
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clock class="h-6 w-6 text-yellow-600" />
                </div>
                <div>
                    <flux:text class="font-bold text-2xl text-yellow-600">{{ $employeesWithPendingOrders->count() }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">{{ __('app.Pending orders') }}</flux:text>
                </div>
            </div>
        </flux:card>

        <!-- Employees with Approved Orders -->
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <flux:icon.check-circle class="h-6 w-6 text-blue-600" />
                </div>
                <div>
                    <flux:text class="font-bold text-2xl text-blue-600">{{ $employeesWithApprovedOrders->count() }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">{{ __('app.Approved orders') }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Detailed Employees with Active Orders -->
    {{-- @if($employeesWithPendingOrders->count() > 0)
        <flux:card class="mb-6">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <flux:icon.information-circle class="h-5 w-5 text-yellow-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <flux:heading size="lg" class="text-yellow-800 mb-3">
                        Empleados con Pedidos Pendientes
                    </flux:heading>
                    <flux:text size="sm" class="text-yellow-700 mb-4">
                        Estos empleados tienen pedidos pendientes que requieren aprobación inmediata.
                    </flux:text>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($employeesWithPendingOrders as $employee)
                            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text class="font-medium text-yellow-900 truncate">
                                        {{ $employee->name }}
                                    </flux:text>
                                    <flux:badge :color="$employee->orders->first()->getStatusColor()" size="sm">
                                        {{ $employee->orders->first()::getStatusOptions()[$employee->orders->first()->status] }}
                                    </flux:badge>
                                </div>
                                <div class="space-y-1">
                                    <flux:text size="xs" class="text-yellow-700">
                                        Departamento: {{ $employee->department }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-yellow-700">
                                        Pedido: {{ $employee->orders->first()->order_number }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-yellow-700">
                                        Total: ${{ number_format($employee->orders->first()->total, 2) }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-yellow-700">
                                        Fecha: {{ $employee->orders->first()->order_date->format('d/m/Y') }}
                                    </flux:text>
                                </div>
                                <div class="mt-2 pt-2 border-t border-yellow-300">
                                    <flux:button 
                                        variant="ghost" 
                                        size="xs" 
                                        href="{{ route('orders.view', $employee->orders->first()->id) }}"
                                        class="w-full text-yellow-800 hover:bg-yellow-200"
                                    >
                                        Ver Detalles
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </flux:card>
    @endif --}}

    <!-- Detailed Employees with Approved Orders -->
    {{-- @if($employeesWithApprovedOrders->count() > 0)
        <flux:card class="mb-6">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <flux:icon.check-circle class="h-5 w-5 text-blue-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <flux:heading size="lg" class="text-blue-800 mb-3">
                        Empleados con Pedidos Aprobados
                    </flux:heading>
                    <flux:text size="sm" class="text-blue-700 mb-4">
                        Estos empleados tienen pedidos aprobados listos para entrega.
                    </flux:text>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($employeesWithApprovedOrders as $employee)
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <flux:text class="font-medium text-blue-900 truncate">
                                        {{ $employee->name }}
                                    </flux:text>
                                    <flux:badge :color="$employee->orders->first()->getStatusColor()" size="sm">
                                        {{ $employee->orders->first()::getStatusOptions()[$employee->orders->first()->status] }}
                                    </flux:badge>
                                </div>
                                <div class="space-y-1">
                                    <flux:text size="xs" class="text-blue-700">
                                        Departamento: {{ $employee->department }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-blue-700">
                                        Pedido: {{ $employee->orders->first()->order_number }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-blue-700">
                                        Total: ${{ number_format($employee->orders->first()->total, 2) }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-blue-700">
                                        Aprobado: {{ $employee->orders->first()->approved_at->format('d/m/Y') }}
                                    </flux:text>
                                </div>
                                <div class="mt-2 pt-2 border-t border-blue-300">
                                    <flux:button 
                                        variant="ghost" 
                                        size="xs" 
                                        href="{{ route('orders.view', $employee->orders->first()->id) }}"
                                        class="w-full text-blue-800 hover:bg-blue-200"
                                    >
                                        Ver Detalles
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </flux:card>
    @endif --}}

    <!-- Filtros -->
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Filtros</flux:heading>
            <flux:text size="sm" class="text-gray-500">{{ $this->orders->total() }} pedido(s)</flux:text>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <flux:input 
                wire:model.live="search" 
                icon="magnifying-glass" 
                placeholder="Buscar pedidos..." 
                label="Buscar"
            />
            <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Estado" label="Estado" clearable>
                <flux:select.option value="">Todos los estados</flux:select.option>
                @foreach($statusOptions as $key => $status)
                    <flux:select.option value="{{ $key }}">{{ $status }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="priorityFilter" variant="listbox" placeholder="Prioridad" label="Prioridad" clearable>
                <flux:select.option value="">Todas las prioridades</flux:select.option>
                @foreach($priorityOptions as $key => $priority)
                    <flux:select.option value="{{ $key }}">{{ $priority }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="employeeFilter" variant="listbox" placeholder="Empleado" label="Empleado" searchable clearable>
                <flux:select.option value="">Todos los empleados</flux:select.option>
                @foreach($employees as $employee)
                    <flux:select.option value="{{ $employee->id }}">{{ $employee->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <!-- Tabla de Pedidos -->
    <flux:table :paginate="$this->orders">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'order_number'" :direction="$sortDirection" wire:click="sort('order_number')">
                Número
            </flux:table.column>
            <flux:table.column>Empleado</flux:table.column>
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
            @foreach ($this->orders as $order)
                <flux:table.row>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:badge variant="pill" color="blue" size="sm">{{ $order->order_number }}</flux:badge>
                            @if(!$order->isWithinPurchaseLimit())
                                <flux:icon.exclamation-triangle class="w-4 h-4 text-orange-500" title="Excede límite de compra" />
                            @endif
                        </div>
                    </flux:table.cell>
                    
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <flux:text size="xs" class="font-bold text-blue-600">
                                    {{ strtoupper(substr($order->employee->name, 0, 2)) }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $order->employee->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $order->employee->department }}</flux:text>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div>
                            <flux:text>{{ $order->order_date->format('d/m/Y') }}</flux:text>
                            <flux:text size="sm" class="text-gray-500 block">{{ $order->created_at->format('h:i A') }}</flux:text>
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
                        <div class="space-y-1">
                            <flux:badge :color="$order->getStatusColor()" size="sm">
                                {{ $order::getStatusOptions()[$order->status] }}
                            </flux:badge>
                            @if($order->status === 'rejected' && $order->rejector)
                                <flux:text size="xs" class="text-red-600 block">
                                    Rechazado por: {{ $order->rejector->name }}
                                </flux:text>
                                @if($order->rejected_at)
                                    <flux:text size="xs" class="text-gray-500 block">
                                        {{ $order->rejected_at->format('d/m/Y h:i A') }}
                                    </flux:text>
                                @endif
                            @endif
                            @if($order->status === 'approved' && $order->approver)
                                <flux:text size="xs" class="text-green-600 block">
                                    Aprobado por: {{ $order->approver->name }}
                                </flux:text>
                                @if($order->approved_at)
                                    <flux:text size="xs" class="text-gray-500 block">
                                        {{ $order->approved_at->format('d/m/Y h:i A') }}
                                    </flux:text>
                                @endif
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('orders.view', $order->id) }}">
                                    Ver Detalles
                                </flux:menu.item>
                                
                                @if($order->canBeApproved())
                                    <flux:menu.separator />
                                    <flux:menu.item icon="check" wire:click="openApprovalModal({{ $order->id }})">
                                        Aprobar
                                    </flux:menu.item>
                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="openRejectionModal({{ $order->id }})">
                                        Rechazar
                                    </flux:menu.item>
                                @endif

                                @if($order->canBeDelivered())
                                    <flux:menu.separator />
                                    <flux:menu.item icon="truck" wire:click="deliverOrder({{ $order->id }})">
                                        Marcar Entregado
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal de Aprobación -->
    <flux:modal name="approval-modal" :open="$showApprovalModal" wire:model="showApprovalModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.check class="h-5 w-5 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">Aprobar Pedido</flux:heading>
                    @if($selectedOrder)
                        <flux:subheading>¿Aprobar el pedido {{ $selectedOrder->order_number }}?</flux:subheading>
                    @endif
                </div>
            </div>

            @if($selectedOrder)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <flux:text class="font-medium">Empleado:</flux:text>
                            <flux:text>{{ $selectedOrder->employee->name }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Total:</flux:text>
                            <flux:text>${{ number_format($selectedOrder->total, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Items:</flux:text>
                            <flux:text>{{ $selectedOrder->items->count() }} productos</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Prioridad:</flux:text>
                            <flux:badge :color="$selectedOrder->getPriorityColor()" size="sm">
                                {{ $selectedOrder::getPriorityOptions()[$selectedOrder->priority] }}
                            </flux:badge>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeApprovalModal">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="approveOrder">
                    Aprobar Pedido
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal de Rechazo -->
    <flux:modal name="rejection-modal" :open="$showRejectionModal" wire:model="showRejectionModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.x-mark class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Rechazar Pedido</flux:heading>
                    @if($selectedOrder)
                        <flux:subheading>Rechazar el pedido {{ $selectedOrder->order_number }}</flux:subheading>
                    @endif
                </div>
            </div>

            <div>
                <flux:textarea 
                    wire:model="rejectionReason" 
                    label="Razón del rechazo" 
                    placeholder="Explica por qué se rechaza este pedido..."
                    rows="4"
                />
                @error('rejectionReason') 
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeRejectionModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="rejectOrder">
                    Rechazar Pedido
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>