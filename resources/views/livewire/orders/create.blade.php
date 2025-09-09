<?php

use App\Models\Product;
use App\Models\Employee;
use App\Models\Category;
use App\Models\ProductCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StoreConfig;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public $search = '';
    public $categoryFilter = '';
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
        
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            Flux::toast(
                heading: 'Tienda Cerrada',
                text: 'La tienda está cerrada actualmente. No se pueden crear nuevos pedidos.',
                variant: 'error',
                position: 'top-right'
            );
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
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
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            Flux::toast(
                heading: 'Tienda Cerrada',
                text: 'No se pueden agregar productos mientras la tienda esté cerrada',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

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
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            Flux::toast(
                heading: 'Tienda Cerrada',
                text: 'No se pueden agregar productos mientras la tienda esté cerrada',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $quantity = $this->productQuantities[$productId] ?? 1;
        $product = Product::find($productId);
        
        if ($product) {
            $this->addToCart($product, $quantity);
        }
    }

    public function updateCartQuantity($productId, $quantity)
    {
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            Flux::toast(
                heading: 'Tienda Cerrada',
                text: 'No se pueden modificar productos mientras la tienda esté cerrada',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

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
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            Flux::toast(
                heading: 'Tienda Cerrada',
                text: 'No se pueden crear pedidos mientras la tienda esté cerrada',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

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
            'order_date' => Carbon::parse($this->order_date)->toDateString(),
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

        $query->when($this->categoryFilter, function ($query) {
            $query->where('product_category_id', $this->categoryFilter);
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
            'productCategories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
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

    <div class=" grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Order Form -->
        <div class="lg:col-span-1">
            <flux:card class="mb-4">
                <flux:heading size="lg" class="mb-4">Información del Pedido</flux:heading>
                
                <div class="space-y-4">
                    <flux:select variant="listbox" wire:model.live="employee_id" placeholder="Seleccionar empleado" label="Empleado" searchable>
                        @foreach($employees as $employee)
                            <flux:select.option value="{{ $employee->id }}">
                                {{ $employee->name }} - {{ $employee->department }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea 
                        wire:model="notes" 
                        label="Notas adicionales (opcional)" 
                        placeholder="Agrega cualquier información adicional..."
                        rows="3"
                    />
                    

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
                                                    {{ $item['quantity'] }} × RD$ {{ number_format($item['price'], 2) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                        <div class="text-right ml-2">
                                            <flux:text size="sm" class="font-bold text-green-600">
                                                RD$ {{ number_format($item['subtotal'], 2) }}
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
                                        RD$ {{ number_format($this->cartTotal, 2) }}
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
            <!-- Search and Filters -->
            <flux:card class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Productos Disponibles</flux:heading>
                    <flux:text size="sm" class="text-gray-500">{{ $this->products->total() }} producto(s)</flux:text>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input 
                        wire:model.live="search" 
                        icon="magnifying-glass" 
                        placeholder="Buscar productos..." 
                        label="Buscar"
                    />
                    <flux:select wire:model.live="categoryFilter" placeholder="Todas las categorías" label="Filtrar por categoría" variant="listbox" searchable>
                        <flux:select.option value="">Todas las categorías</flux:select.option>
                        @foreach($productCategories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:card>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 mb-8">
                @foreach ($this->products as $product)
                    <div class="flex flex-col h-full rounded-lg border {{ isset($cart[$product->id]) ? 'border-2 border-blue-500 shadow-sm' : 'border-gray-200' }} bg-white overflow-hidden relative">
                        
                        @if(isset($cart[$product->id]))
                            <div class="absolute top-2 right-2 z-10">
                                <flux:button 
                                    size="xs" 
                                    icon="trash" 
                                    variant="danger"
                                    wire:click.stop="removeFromCart({{ $product->id }})"
                                    class="w-8 h-8 p-0 shadow-sm"
                                />
                            </div>
                        @endif
                        
                        <div class="flex-1">
                            <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden relative">
                                @if($product->getFirstMediaUrl('images'))
                                    <img src="{{ $product->getFirstMediaUrl('images') }}" 
                                         alt="{{ $product->description }}" 
                                         class="w-full h-full object-cover">
                                @else
                                    <flux:icon.cube class="h-12 w-12 text-gray-400" />
                                @endif

                                <div class="absolute bottom-2 left-2 z-10">
                                    @if($product->productCategory && $product->productCategory->name == 'Cuadernos')
                                        <flux:badge variant="solid" color="blue" size="sm">
                                            El diseño puede variar.
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="p-3">
                                <flux:text class="font-medium mb-2 text-sm sm:text-base">{{ $product->description }}</flux:text>
                                
                                <div class="text-right mb-3">
                                    <flux:text class="font-bold text-lg text-blue-600">
                                        RD$ {{ number_format($product->price, 2) }}
                                    </flux:text>
                                </div>

                                <!-- Quantity Controls -->
                                <div class="">
                                    @if(isset($cart[$product->id]))
                                        <div class="flex justify-between items-center gap-2">
                                            <flux:button 
                                                size="xs" 
                                                icon="minus" 
                                                variant="ghost"
                                                wire:click.stop="updateCartQuantity({{ $product->id }}, {{ $cart[$product->id]['quantity'] - 1 }})"
                                                class="w-8 h-8 p-0"
                                            />
                                            <flux:text class="font-medium text-sm min-w-[2rem] text-center">
                                                {{ $cart[$product->id]['quantity'] }}
                                            </flux:text>
                                            <flux:button 
                                                size="xs" 
                                                icon="plus" 
                                                variant="ghost"
                                                wire:click.stop="updateCartQuantity({{ $product->id }}, {{ $cart[$product->id]['quantity'] + 1 }})"
                                                class="w-8 h-8 p-0"
                                            />
                                        </div>
                                        
                                    @else
                                        <flux:button 
                                            size="sm" 
                                            icon="plus" 
                                            variant="primary"
                                            wire:click.stop="addToCartWithQuantity({{ $product->id }})"
                                            class="flex-1 w-full"
                                        >
                                            Agregar
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
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
        <div class="space-y-6">
            <!-- Cart Header -->
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Carrito de Compras</flux:heading>
            </div>

            <!-- Cart Items -->
            <div class="max-h-96 overflow-y-auto">
                @if(empty($cart))
                    <div class="text-center py-8">
                        <flux:icon.shopping-cart class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <flux:text class="text-gray-500">Tu carrito está vacío</flux:text>
                        <flux:text size="sm" class="text-gray-400 block">Agrega productos para comenzar</flux:text>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($cart as $productId => $item)
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <!-- Product Info -->
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-white">
                                        @if($item['product']->getFirstMediaUrl('images'))
                                            <flux:avatar circle src="{{ $item['product']->getFirstMediaUrl('images') }}" alt="{{ $item['product']->description }}"/>
                                        @else
                                            <flux:icon.cube class="h-8 w-8 text-gray-400" />
                                        @endif
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate text-sm sm:text-base">{{ $item['product']->description }}</flux:text>
                                        <flux:text size="sm" class="text-gray-600">RD$ {{ number_format($item['price'], 2) }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500">Subtotal: RD$ {{ number_format($item['subtotal'], 2) }}</flux:text>
                                    </div>
                                </div>
                                
                                <!-- Quantity Controls -->
                                <div class="flex items-center justify-between sm:justify-end gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:button 
                                            size="xs" 
                                            icon="minus" 
                                            variant="ghost"
                                            wire:click="updateCartQuantity({{ $productId }}, {{ $item['quantity'] - 1 }})"
                                            class="w-8 h-8 p-0"
                                        />
                                        <flux:text class="font-medium text-sm min-w-[2rem] text-center">
                                            {{ $item['quantity'] }}
                                        </flux:text>
                                        <flux:button 
                                            size="xs" 
                                            icon="plus" 
                                            variant="ghost"
                                            wire:click="updateCartQuantity({{ $productId }}, {{ $item['quantity'] + 1 }})"
                                            class="w-8 h-8 p-0"
                                        />
                                    </div>
                                    <flux:button 
                                        variant="danger" 
                                        icon="trash"
                                        size="xs"
                                        wire:click="removeFromCart({{ $productId }})"
                                        class="w-8 h-8 p-0"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Cart Footer -->
            @if(!empty($cart))
                <div class="border-t pt-4 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                        <div>
                            <flux:text class="font-bold text-lg">Total:</flux:text>
                            <flux:text size="sm" class="text-gray-600">{{ $this->cartCount }} productos</flux:text>
                        </div>
                        <flux:text class="font-bold text-xl text-green-600">
                            RD$ {{ number_format($this->cartTotal, 2) }}
                        </flux:text>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-2">
                        <flux:button variant="ghost" class="flex-1 sm:flex-none" wire:click="clearCart">
                            Vaciar Carrito
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

</div>