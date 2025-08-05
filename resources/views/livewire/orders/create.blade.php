<?php

use App\Models\Product;
use App\Models\Employee;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public $search = '';
    public $perPage = 12;
    
    // Order form
    public $employee_id = '';
    public $priority = 'medium';
    public $notes = '';
    public $order_date = '';
    
    // Cart functionality
    public $cart = [];
    public $showCart = false;
    public $productQuantities = [];

    public function mount()
    {
        $this->order_date = now()->toDateString();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedEmployeeId()
    {
        // Clear cart when employee changes
        $this->cart = [];
    }

    public function addToCart(Product $product, $quantity = 1)
    {
        if (!$this->employee_id) {
            Flux::toast(
                heading: 'Error',
                text: 'Selecciona un empleado primero',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $productId = $product->id;
        
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $quantity;
            $this->cart[$productId]['subtotal'] = $this->cart[$productId]['price'] * $this->cart[$productId]['quantity'];
            
            Flux::toast(
                heading: 'Producto actualizado',
                text: "Se agregaron {$quantity} unidades más de {$product->description}. Total: {$this->cart[$productId]['quantity']} unidades",
                variant: 'success',
                position: 'top-right'
            );
        } else {
            $this->cart[$productId] = [
                'product' => $product,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => $product->price * $quantity,
            ];
            
            Flux::toast(
                heading: 'Producto agregado',
                text: "Se agregó {$product->description} al carrito",
                variant: 'success',
                position: 'top-right'
            );
        }
    }

    public function addToCartWithQuantity($productId)
    {
        $quantity = $this->productQuantities[$productId] ?? 1;
        $product = Product::find($productId);
        
        if ($product) {
            $this->addToCart($product, $quantity);
        }
    }

    public function updateCartQuantity($productId, $quantity)
    {
        if ($quantity <= 0) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]['quantity'] = $quantity;
            $this->cart[$productId]['subtotal'] = $this->cart[$productId]['price'] * $quantity;
        }

        $this->updateCartTotals();
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
        $this->updateCartTotals();
        
        Flux::toast(
            heading: 'Producto eliminado',
            text: 'El producto fue eliminado del carrito',
            variant: 'success',
            position: 'top-right'
        );
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->updateCartTotals();
    }

    private function updateCartTotals()
    {
        foreach ($this->cart as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
        }
    }

    public function getCartTotalProperty()
    {
        return collect($this->cart)->sum('subtotal');
    }

    public function getCartCountProperty()
    {
        return collect($this->cart)->sum('quantity');
    }

    public function toggleCart()
    {
        $this->showCart = !$this->showCart;
    }

    public function createOrder()
    {
        $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'order_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ], [
            'employee_id.required' => 'Debes seleccionar un empleado',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'priority.required' => 'La prioridad es obligatoria',
            'priority.in' => 'La prioridad seleccionada no es válida',
            'order_date.required' => 'La fecha del pedido es obligatoria',
            'order_date.date' => 'La fecha del pedido no es válida',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres',
        ]);

        if (empty($this->cart)) {
            Flux::toast(
                heading: 'Error',
                text: 'Agrega productos al carrito antes de crear el pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $employee = Employee::find($this->employee_id);

        // Calculate totals
        $subtotal = $this->cartTotal;
        $total = $subtotal;

        // Create the order
        $order = Order::create([
            'employee_id' => $this->employee_id,
            'category_id' => $employee->category_id,
            'order_date' => $this->order_date,
            'subtotal' => $subtotal,
            'total' => $total,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'status' => 'pending',
        ]);

        // Create order items
        foreach ($this->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'status' => 'pending',
            ]);
        }

        Flux::toast(
            heading: 'Pedido creado',
            text: "El pedido #{$order->order_number} ha sido creado exitosamente",
            variant: 'success',
            position: 'top-right'
        );

        // Redirect to orders list
        return redirect()->route('orders.index');
    }

    public function getProductsProperty()
    {
        $query = Product::query();
        
        $query->when($this->search, function ($query) {
            $query->where('description', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%");
        });
        
        return $query->orderBy('description')->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'employees' => Employee::where('active', true)
                ->whereDoesntHave('orders', function ($query) {
                    $query->whereIn('status', ['pending', 'approved']);
                })
                ->orderBy('name')
                ->get(),
            'employeesWithPendingOrders' => Employee::where('active', true)
                ->whereHas('orders', function ($query) {
                    $query->whereIn('status', ['pending', 'approved']);
                })
                ->with(['orders' => function ($query) {
                    $query->whereIn('status', ['pending', 'approved'])->latest();
                }])
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">Crear Nuevo Pedido</flux:heading>
            <flux:subheading>Crear pedido interno para empleado</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="arrow-left" href="{{ route('orders.index') }}" variant="ghost" size="sm">
                Volver a Pedidos
            </flux:button>
            
            <!-- Cart Button -->
            <flux:button 
                variant="outline" 
                wire:click="toggleCart"
                class="relative"
                size="sm"
            >
                🛒 Carrito
                @if($this->cartCount > 0)
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        {{ $this->cartCount }}
                    </span>
                @endif
            </flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-6"/>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Order Form -->
        <div class="lg:col-span-1">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Información del Pedido</flux:heading>
                
                <div class="space-y-4">
                    <flux:select wire:model.live="employee_id" placeholder="Seleccionar empleado" label="Empleado">
                        @foreach($employees as $employee)
                            <flux:select.option value="{{ $employee->id }}">
                                {{ $employee->name }} - {{ $employee->department }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('employee_id') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror

                    @if($employeesWithPendingOrders->count() > 0)
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <flux:icon.information-circle class="h-4 w-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <flux:text size="sm" class="font-medium text-yellow-800">
                                        Empleados con pedidos pendientes/aprobados:
                                    </flux:text>
                                    <div class="mt-1 space-y-1">
                                        @foreach($employeesWithPendingOrders as $employee)
                                            <div class="flex items-center justify-between">
                                                <flux:text size="xs" class="text-yellow-700">
                                                    {{ $employee->name }}
                                                </flux:text>
                                                <flux:badge :color="$employee->orders->first()->getStatusColor()" size="xs">
                                                    {{ $employee->orders->first()::getStatusOptions()[$employee->orders->first()->status] }}
                                                </flux:badge>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <flux:input 
                        wire:model="order_date" 
                        type="date" 
                        label="Fecha del pedido"
                    />
                    @error('order_date') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror

                    <flux:select wire:model="priority" label="Prioridad" placeholder="Selecciona la prioridad">
                        <flux:select.option value="low">Baja</flux:select.option>
                        <flux:select.option value="medium">Media</flux:select.option>
                        <flux:select.option value="high">Alta</flux:select.option>
                        <flux:select.option value="urgent">Urgente</flux:select.option>
                    </flux:select>
                    @error('priority') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror

                    <flux:textarea 
                        wire:model="notes" 
                        label="Notas adicionales (opcional)" 
                        placeholder="Agrega cualquier información adicional..."
                        rows="3"
                    />
                    @error('notes') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror

                    @if(!empty($cart))
                        <div class="pt-4 border-t">
                            <flux:heading size="md" class="mb-3">Resumen del Pedido</flux:heading>
                            
                            <!-- Cart Items -->
                            <div class="space-y-2 mb-4 max-h-64 overflow-y-auto">
                                @foreach($cart as $productId => $item)
                                    <div class="flex items-start justify-between p-2 bg-gray-50 rounded-lg">
                                        <div class="flex-1 min-w-0">
                                            <flux:text size="sm" class="font-medium truncate">
                                                {{ $item['product']->description }}
                                            </flux:text>
                                            <flux:text size="xs" class="text-gray-600">
                                                {{ $item['product']->code }}
                                            </flux:text>
                                            <div class="flex items-center gap-2 mt-1">
                                                <flux:text size="xs" class="text-gray-500">
                                                    {{ $item['quantity'] }} × ${{ number_format($item['price'], 2) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                        <div class="text-right ml-2">
                                            <flux:text size="sm" class="font-bold text-green-600">
                                                ${{ number_format($item['subtotal'], 2) }}
                                            </flux:text>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- Total -->
                            <div class="border-t pt-3 mb-4">
                                <div class="flex justify-between items-center">
                                    <flux:text class="font-bold">Total del Pedido:</flux:text>
                                    <flux:text class="font-bold text-xl text-green-600">
                                        ${{ number_format($this->cartTotal, 2) }}
                                    </flux:text>
                                </div>
                                <flux:text size="sm" class="text-gray-500">
                                    {{ $this->cartCount }} producto(s) • {{ collect($cart)->count() }} artículo(s) diferente(s)
                                </flux:text>
                            </div>
                            
                            <flux:button 
                                variant="primary" 
                                class="w-full" 
                                wire:click="createOrder"
                                icon="plus"
                            >
                                Crear Pedido
                            </flux:button>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

        <!-- Products Grid -->
        <div class="lg:col-span-3">
            <!-- Search -->
            <flux:card class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Productos Disponibles</flux:heading>
                    <flux:text size="sm" class="text-gray-500">{{ $this->products->total() }} producto(s)</flux:text>
                </div>
                <flux:input 
                    wire:model.live="search" 
                    icon="magnifying-glass" 
                    placeholder="Buscar productos..." 
                    label="Buscar"
                />
            </flux:card>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                @foreach ($this->products as $product)
                    <flux:card class="flex flex-col h-full">
                        <div class="flex-1">
                            <div class="aspect-square bg-gray-100 rounded-lg mb-4 flex items-center justify-center">
                                <flux:icon.cube class="h-12 w-12 text-gray-400" />
                            </div>
                            
                            <flux:text class="font-medium text-base mb-2">{{ $product->description }}</flux:text>
                            <flux:text size="sm" class="text-gray-600 mb-2">Código: {{ $product->code }}</flux:text>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <flux:icon.currency-dollar class="h-4 w-4 text-green-600" />
                                    <flux:text class="font-bold text-lg text-green-600">
                                        ${{ number_format($product->price, 2) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t">
                            <div class="flex items-center gap-2">
                                <flux:input 
                                    type="number" 
                                    min="1" 
                                    value="1" 
                                    size="sm"
                                    class="w-16"
                                    wire:model="productQuantities.{{ $product->id }}"
                                />
                                <flux:button 
                                    variant="primary" 
                                    size="sm" 
                                    icon="plus"
                                    class="flex-1"
                                    wire:click="addToCartWithQuantity({{ $product->id }})"
                                >
                                    Agregar
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $this->products->links() }}
            </div>
        </div>
    </div>

    <!-- Cart Modal Flyout -->
    <flux:modal name="cart-modal" variant="flyout" wire:model="showCart">
        <div class="h-full flex flex-col">
            <!-- Cart Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <flux:heading size="lg">Carrito de Compras</flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="toggleCart">
                    ×
                </flux:button>
            </div>

            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-4">
                @if(empty($cart))
                    <div class="text-center py-8">
                        <flux:icon.shopping-cart class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <flux:text class="text-gray-500">El carrito está vacío</flux:text>
                        <flux:text size="sm" class="text-gray-400 block">Agrega productos para comenzar</flux:text>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($cart as $productId => $item)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center">
                                    <flux:icon.cube class="h-6 w-6 text-gray-500" />
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-medium truncate">{{ $item['product']->description }}</flux:text>
                                    <flux:text size="sm" class="text-gray-600">${{ number_format($item['price'], 2) }}</flux:text>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <flux:input 
                                        type="number" 
                                        min="1" 
                                        value="{{ $item['quantity'] }}"
                                        size="sm"
                                        class="w-16"
                                        wire:change="updateCartQuantity({{ $productId }}, $event.target.value)"
                                    />
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm"
                                        wire:click="removeFromCart({{ $productId }})"
                                    >
                                        🗑️
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Cart Footer -->
            @if(!empty($cart))
                <div class="border-t p-4 space-y-4">
                    <div class="flex justify-between items-center">
                        <flux:text class="font-bold text-lg">Total:</flux:text>
                        <flux:text class="font-bold text-xl text-green-600">
                            ${{ number_format($this->cartTotal, 2) }}
                        </flux:text>
                    </div>
                    
                    <div class="space-y-2">
                        <flux:button variant="ghost" class="w-full" wire:click="clearCart">
                            Vaciar Carrito
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

</div>