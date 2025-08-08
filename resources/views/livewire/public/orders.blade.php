<?php

use App\Models\Product;
use App\Models\Employee;
use App\Models\Category;
use App\Models\ProductCategory;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.public')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public $search = '';
    public $categoryFilter = '';
    public $perPage = 12;
    
    // Cart functionality
    public $cart = [];
    public $showCart = false;
    public $showOrderModal = false;
    public $showClearCartModal = false;
    public $showRemoveItemModal = false;
    public $itemToRemove = null;
    public $productQuantities = [];
    public $pendingOrders = 0;
    
    // Order form
    public $priority = 'medium';
    public $notes = '';
    
    // Employee data
    public $employee = null;

    public function mount()
    {
        // Get current authenticated user's employee record
        $this->employee = auth()->user()->employee;
        
        if (!$this->employee) {
            return redirect()->route('login')->with('error', 'No tienes permisos para acceder a esta página');
        }

        // Check if user has pending orders
        $this->pendingOrders = Order::where('employee_id', $this->employee->id)
            ->whereIn('status', ['pending'])
            ->count();

        // if ($this->pendingOrders > 0) {
        //     Flux::toast(
        //         heading: 'Pedido pendiente',
        //         text: 'Ya tienes un pedido en proceso. No puedes crear otro pedido hasta que se complete el anterior.',
        //         variant: 'warning',
        //         position: 'top-right'
        //     );
        // }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function addToCart(Product $product, $quantity = 1)
    {
        $productId = $product->id;
        
        // Check purchase limit before adding to cart
        $user = auth()->user();
        $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
        
        if ($purchaseLimit) {
            $newTotal = $this->cartTotal + ($product->price * $quantity);
            
            if ($newTotal > $purchaseLimit) {
                Flux::toast(
                    heading: 'Límite excedido',
                    text: "No puedes agregar este producto. El total excedería tu límite de RD$ " . number_format($purchaseLimit, 2),
                    variant: 'error',
                    position: 'top-right'
                );
                return;
            }
        }
        
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
            // Check purchase limit before updating quantity
            $user = auth()->user();
            $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
            
            if ($purchaseLimit) {
                $currentItemTotal = $this->cart[$productId]['subtotal'] ?? 0;
                $newItemTotal = $this->cart[$productId]['price'] * $quantity;
                $newTotal = $this->cartTotal - $currentItemTotal + $newItemTotal;
                
                if ($newTotal > $purchaseLimit) {
                    Flux::toast(
                        heading: 'Límite excedido',
                        text: "No puedes aumentar la cantidad. El total excedería tu límite de RD$ " . number_format($purchaseLimit, 2),
                        variant: 'error',
                        position: 'top-right'
                    );
                    return;
                }
            }
            
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
        $this->showClearCartModal = false;
        
        Flux::toast(
            heading: 'Carrito vaciado',
            text: 'Todos los productos han sido removidos del carrito',
            variant: 'success',
            position: 'top-right'
        );
    }

    public function openClearCartModal()
    {
        $this->showClearCartModal = true;
    }

    public function closeClearCartModal()
    {
        $this->showClearCartModal = false;
    }

    public function openRemoveItemModal($productId)
    {
        $this->itemToRemove = $productId;
        $this->showRemoveItemModal = true;
    }

    public function closeRemoveItemModal()
    {
        $this->showRemoveItemModal = false;
        $this->itemToRemove = null;
    }

    public function confirmRemoveItem()
    {
        if ($this->itemToRemove) {
            $this->removeFromCart($this->itemToRemove);
            $this->closeRemoveItemModal();
        }
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

    public function getRemainingAmountProperty()
    {
        $user = auth()->user();
        $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
        
        if (!$purchaseLimit) {
            return null;
        }
        
        return $purchaseLimit - $this->cartTotal;
    }

    public function getIsNearLimitProperty()
    {
        $user = auth()->user();
        $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
        
        if (!$purchaseLimit) {
            return false;
        }
        
        $remainingAmount = $this->remainingAmount;
        $tenPercentOfLimit = $purchaseLimit * 0.1;
        
        return $remainingAmount > 0 && $remainingAmount <= $tenPercentOfLimit;
    }

    public function toggleCart()
    {
        $this->showCart = !$this->showCart;
        Flux::modal('cart-modal')->show();
    }

    public function openCart()
    {
        // $this->showCart = true;
        Flux::modal('cart-modal')->show();
    }

    public function closeCart()
    {
        // $this->showCart = false;
        Flux::modal('cart-modal')->close();
    }

    public function openOrderModal()
    {
        if (empty($this->cart)) {
            Flux::toast(
                heading: 'Carrito vacío',
                text: 'Agrega productos al carrito antes de crear un pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        Flux::modal('order-modal')->show();
    }

    public function closeOrderModal()
    {
        Flux::modal('order-modal')->close();
        $this->priority = 'medium';
        $this->notes = '';
    }

    public function createOrder()
    {
        if (empty($this->cart)) {
            Flux::toast(
                heading: 'Error',
                text: 'El carrito está vacío',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        // Check if user has pending orders
        $pendingOrders = Order::where('employee_id', $this->employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        if ($pendingOrders > 0) {
            Flux::toast(
                heading: 'Pedido pendiente',
                text: 'Ya tienes un pedido en proceso. No puedes crear otro pedido hasta que se complete el anterior.',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        // Check purchase limit
        $user = auth()->user();
        $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
        
        if ($purchaseLimit && $this->cartTotal > $purchaseLimit) {
            Flux::toast(
                heading: 'Límite excedido',
                text: "Tu límite de compra es RD$ " . number_format($purchaseLimit, 2) . ". El total de tu carrito es RD$ " . number_format($this->cartTotal, 2),
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->validate([
            'priority' => 'required|in:low,medium,high,urgent',
            'notes' => 'nullable|string|max:1000',
        ], [
            'priority.required' => 'La prioridad es obligatoria',
            'priority.in' => 'La prioridad seleccionada no es válida',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres',
        ]);

        // Calculate totals
        $subtotal = $this->cartTotal;
        $total = $subtotal;

        // Create the order
        $order = Order::create([
            'employee_id' => $this->employee->id,
            'category_id' => $user->category_id,
            'order_date' => now()->toDateString(),
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

        // Clear cart and close modal
        $this->clearCart();
        $this->closeOrderModal();

        Flux::toast(
            heading: 'Pedido creado',
            text: "Tu pedido #{$order->order_number} ha sido enviado para aprobación",
            variant: 'success',
            position: 'top-right'
        );

        // Redirect to orders history
        return redirect()->route('public.orders.history');
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
            'categories' => Category::orderBy('code')->get(),
            'productCategories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
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
                <flux:button variant="ghost" href="{{ route('public.orders.history') }}" icon="clipboard-document-list" class="flex items-center gap-2">
                    Mis Pedidos
                </flux:button>
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" color="blue" wire:click="openCart" class="relative" icon="shopping-cart">
                        Carrito
                        @if($this->cartCount > 0)
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ $this->cartCount }}
                            </span>
                        @endif
                    </flux:button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" icon:trailing="log-out">
                            Salir
                        </flux:button>
                    </form>
                </div>
                
                
                {{-- <div class="flex justify-between items-center gap-4">
                    <!-- User Info -->
                    <div class="text-right">
                        <flux:text class="font-medium">{{ $this->employee->name }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 block">{{ $this->employee->department }}</flux:text>
                    </div>
                    
                    <!-- Cart Button -->
                    
                    <!-- My Orders -->
                    
                    <!-- Logout -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost">
                            Salir
                        </flux:button>
                    </form>
                </div> --}}
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="mb-6 sm:mb-8">
            <flux:heading size="xl" class="sm:text-2xl text-gray-900 text-center sm:text-left">¡Bienvenido, {{ $this->employee->name }}!</flux:heading>
            <flux:subheading class="text-gray-600 text-center sm:text-left">Selecciona los productos que necesitas para tu familia.</flux:subheading>
        </div>

        {{-- pedido pendiente --}}
        @if($pendingOrders > 0)
            <flux:callout variant="warning" icon="clock" class="mb-8">
                <flux:callout.heading>Pedido pendiente</flux:callout.heading>
                <flux:callout.text>Tu pedido está pendiente de aprobación. Por favor, espera a que sea aprobado para poder verlo en el historial de pedidos.</flux:callout.text>
                <x-slot name="actions">
                    <flux:button>Ir a mis pedidos</flux:button>
                </x-slot>
            </flux:callout>
        @endif
        <!-- Filters -->
        <flux:card class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <flux:heading size="lg">Productos Disponibles</flux:heading>
                    <flux:text size="sm" class="text-gray-500">{{ $this->products->total() }} producto(s)</flux:text>
                </div>
                {{-- @if(!empty($cart))
                    <flux:button 
                        variant="ghost" 
                        size="sm" 
                        icon="shopping-cart"
                        wire:click="openCart"
                    >
                        Ver Carrito ({{ $this->cartCount }})
                    </flux:button>
                @endif --}}
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
                                wire:click.stop="openRemoveItemModal({{ $product->id }})"
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
                                @if($product->category->name == 'Cuadernos')
                                    <flux:badge variant="solid" color="blue" size="sm">
                                        El diseño puede variar.
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                        
                        <div class="p-3">
                            <flux:text class="font-medium mb-2 line-clamp-2 text-sm sm:text-base">{{ $product->description }}</flux:text>
                            
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
        <div class="mt-6 mb-24 sm:mb-20">
            {{ $this->products->links() }}
        </div>
    </div>

    @if(!empty($cart))
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg p-3 sm:p-4 z-50">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                <div class="flex-1">
                    <flux:heading size="lg" class="text-center sm:text-left">
                        Total: RD$ {{ number_format($this->cartTotal, 2) }}
                    </flux:heading>
                    <flux:text size="sm" class="text-gray-600 text-center sm:text-left">
                        {{ $this->cartCount }} productos en el carrito
                    </flux:text>
                </div>
                @php
                    $user = auth()->user();
                    $purchaseLimit = $user->category ? $user->category->purchase_limit : null;
                    $pendingOrders = \App\Models\Order::where('employee_id', $this->employee->id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->count();
                    $canCreateOrder = $pendingOrders === 0 && (!$purchaseLimit || $this->cartTotal <= $purchaseLimit);
                @endphp
                <div class="flex gap-2">
                    <flux:button 
                        variant="ghost" 
                        size="sm" 
                        icon="trash"
                        wire:click="openClearCartModal"
                    >
                        Vaciar
                    </flux:button>
                    <flux:button 
                        variant="primary" 
                        color="blue" 
                        size="sm" 
                        wire:click="openOrderModal"
                        :disabled="$pendingOrders > 0"
                        class="flex-1 sm:flex-none"
                    >
                        Crear Pedido
                    </flux:button>
                </div>
            </div>
            @if($purchaseLimit)
                <div class="mt-2 text-center sm:text-left">
                    <flux:text size="sm" class="text-gray-500">
                        Límite: RD$ {{ number_format($purchaseLimit, 2) }}
                        @if($this->cartTotal > $purchaseLimit)
                            <span class="text-red-500"> - Límite excedido</span>
                        @elseif($this->isNearLimit)
                            <span class="text-orange-500"> - Te quedan RD$ {{ number_format($this->remainingAmount, 2) }}</span>
                        @endif
                    </flux:text>
                @else
                    <flux:text class="text-gray-500">
                        Sin límite de compra
                    </flux:text>
                @endif
                @if($pendingOrders > 0)
                    <flux:text class="text-red-500 block text-sm">
                        Ya tienes un pedido en proceso
                    </flux:text>
                @endif
            </div>
        </div>
    @endif
    <!-- Cart Modal -->
    <flux:modal name="cart-modal" variant="flyout">
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
                                        wire:click="openRemoveItemModal({{ $productId }})"
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
                        <flux:button variant="primary" class="flex-1" wire:click="openOrderModal">
                            Crear Pedido
                        </flux:button>
                        <flux:button variant="ghost" class="flex-1 sm:flex-none" wire:click="openClearCartModal">
                            Vaciar Carrito
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Order Modal -->
    <flux:modal name="order-modal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clipboard-document-list class="h-5 w-5 text-blue-600" />
                </div>
                <div>
                    <flux:heading size="lg">Crear Nuevo Pedido</flux:heading>
                    <flux:subheading>Completa la información del pedido</flux:subheading>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="">
                <flux:text class="font-medium mb-3">Resumen del pedido:</flux:text>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @foreach($cart as $item)
                        <div class="flex items-start justify-between p-2 bg-white rounded">
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="font-medium truncate">
                                    {{ $item['product']->description }}
                                </flux:text>
                                <flux:text size="xs" class="text-gray-600">
                                    {{ $item['product']->code }}
                                </flux:text>
                                <flux:text size="xs" class="text-gray-500 mt-1">
                                    {{ $item['quantity'] }} × RD$ {{ number_format($item['price'], 2) }}
                                </flux:text>
                            </div>
                            <div class="text-right ml-2">
                                <flux:text size="sm" class="font-bold text-green-600">
                                    RD$ {{ number_format($item['subtotal'], 2) }}
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t pt-2 mt-3 flex justify-between font-medium">
                    <span>Total:</span>
                    <span class="text-lg font-bold text-green-600">RD$ {{ number_format($this->cartTotal, 2) }}</span>
                </div>
                <flux:text size="xs" class="text-gray-600 mt-1">
                    {{ $this->cartCount }} producto(s) • {{ collect($cart)->count() }} artículo(s) diferente(s)
                </flux:text>
            </div>

            <!-- Form Fields -->
            <div class="space-y-4">
                <flux:textarea 
                    wire:model="notes" 
                    label="Notas adicionales (opcional)" 
                    placeholder="Agrega cualquier información adicional sobre tu pedido..."
                    rows="3"
                />
                @error('notes') 
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeOrderModal">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="createOrder">
                    Crear Pedido
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Clear Cart Confirmation Modal -->
    <flux:modal name="clear-cart-modal" :open="$showClearCartModal" wire:model="showClearCartModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Vaciar Carrito</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres vaciar el carrito?</flux:subheading>
                </div>
            </div>

            <flux:text class="text-gray-600">
                Esta acción eliminará todos los productos del carrito y no se puede deshacer.
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeClearCartModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="clearCart">
                    Vaciar Carrito
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Remove Item Confirmation Modal -->
    <flux:modal name="remove-item-modal" :open="$showRemoveItemModal" wire:model="showRemoveItemModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Eliminar Producto</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres eliminar este producto del carrito?</flux:subheading>
                </div>
            </div>

            @if($itemToRemove && isset($cart[$itemToRemove]))
                <div class="bg-gray-50 rounded-lg p-3">
                    <flux:text class="font-medium">{{ $cart[$itemToRemove]['product']->description }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Cantidad: {{ $cart[$itemToRemove]['quantity'] }} × RD$ {{ number_format($cart[$itemToRemove]['price'], 2) }}
                    </flux:text>
                </div>
            @endif

            <flux:text class="text-gray-600">
                Esta acción eliminará el producto del carrito y no se puede deshacer.
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeRemoveItemModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="confirmRemoveItem">
                    Eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>