<?php

use App\Models\Product;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public $search = '';
    public $perPage = 10;
    public $sortBy = 'id';
    public $sortDirection = 'desc';
    public $showModal = false;
    public $showDeleteModal = false;
    public $editingProduct = null;
    public $productToDelete = null;

    #[Validate('required|string')]
    public $code = '';

    #[Validate('required|string')]
    public $description = '';

    #[Validate('required|numeric|min:0')]
    public $price = '';

    #[Validate('nullable|image|max:2048')]
    public $image = '';

    public function updatedSearch()
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

    public function openModal()
    {
        $this->reset(['code', 'description', 'price', 'image', 'editingProduct']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['code', 'description', 'price', 'image', 'editingProduct']);
        $this->resetValidation();
    }

    public function save()
    {
        $validated = $this->validate([
            'code' => 'required|string|unique:products,code' . ($this->editingProduct ? ',' . $this->editingProduct->id : ''),
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $productData = [
            'code' => $validated['code'],
            'description' => $validated['description'],
            'price' => $validated['price'],
        ];

        if ($this->editingProduct) {
            $this->editingProduct->update($productData);
            $product = $this->editingProduct;
            Flux::toast(
                heading: 'Producto actualizado',
                text: 'El producto ha sido actualizado exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        } else {
            $product = Product::create($productData);
            Flux::toast(
                heading: 'Producto creado',
                text: 'El producto ha sido creado exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        }

        // Handle image upload
        if ($this->image) {
            $product->clearMediaCollection('images');
            $product->addMedia($this->image->getRealPath())
                   ->toMediaCollection('images', 'public');
        }

        $this->closeModal();
    }

    public function edit(Product $product)
    {
        $this->editingProduct = $product;
        $this->code = $product->code;
        $this->description = $product->description;
        $this->price = $product->price;
        $this->image = null;
        $this->showModal = true;
    }

    public function delete(Product $product)
    {
        $product->delete();
        Flux::toast(
            heading: 'Producto eliminado',
            text: 'El producto ha sido eliminado exitosamente.',
            variant: 'success',
            position: 'top-right',
        );
        $this->showDeleteModal = false;
        $this->productToDelete = null;
    }

    public function openDeleteModal(Product $product)
    {
        $this->productToDelete = $product;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->productToDelete = null;
    }

    public function getProductsProperty()
    {
        $query = Product::query();
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('code', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%");
        });
        
        return $query->paginate($this->perPage);
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">Productos</flux:heading>
            <flux:subheading>Se muestran todos los productos</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" wire:click="openModal" variant="primary" size="sm">Agregar Producto</flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-1"/>
    
    <div class="flex justify-end gap-2">
        <div class="flex items-center gap-1 text-xs">
            <flux:text>{{ __('app.Showing') }}</flux:text>
            <flux:text variant="strong">{{ $this->products->firstItem() }}</flux:text>
            <flux:text>{{ __('app.of') }}</flux:text>
            <flux:text variant="strong">{{ $this->products->lastItem() }}</flux:text>
            <flux:text>{{ __('app.of') }}</flux:text>
            <flux:text variant="strong">{{ $this->products->total() }}</flux:text>
            <flux:text>{{ __('app.entries') }}</flux:text>
        </div>
    </div>

    <div class="py-4 flex gap-4">
        <div class="flex-1">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('app.Search') }}..." label="{{ __('app.Search') }}"/>
        </div>
    </div>

    <flux:table :paginate="$this->products">
        <flux:table.columns>
            <flux:table.column>Imagen</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">{{ __('app.Code') }}</flux:table.column>
            <flux:table.column>{{ __('app.Description') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'price'" :direction="$sortDirection" wire:click="sort('price')">{{ __('app.Price') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->products as $item)
                <flux:table.row>
                    <flux:table.cell>
                        @if($item->getFirstMediaUrl('images'))
                            <img src="{{ $item->getFirstMediaUrl('images') }}" 
                                 alt="{{ $item->description }}" 
                                 class="w-12 h-12 object-cover rounded">
                        @else
                            <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                <flux:icon.cube class="h-6 w-6 text-gray-400" />
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->code }}</flux:table.cell>
                    <flux:table.cell>{{ $item->description }}</flux:table.cell>
                    <flux:table.cell>${{ number_format($item->price, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" icon="pencil" wire:click="edit({{ $item->id }})" />
                        <flux:button size="sm" icon="trash" variant="danger" wire:click="openDeleteModal({{ $item->id }})" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal -->
    <flux:modal name="product-modal" :open="$showModal" wire:model="showModal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingProduct ? __('app.Edit Product') : __('app.Add Product') }}</flux:heading>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:input 
                        wire:model="code" 
                        label="{{ __('app.Code') }}" 
                        placeholder="{{ __('app.Code') }}"
                    />
                    @error('code') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div>
                    <flux:textarea 
                        wire:model="description" 
                        label="{{ __('app.Description') }}" 
                        placeholder="{{ __('app.Description') }}"
                        rows="3"
                    />
                    @error('description') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div>
                    <flux:input 
                        wire:model="price" 
                        label="{{ __('app.Price') }}" 
                        type="number" 
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                    />
                    @error('price') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div>
                    <flux:input 
                        wire:model="image" 
                        label="Imagen del producto" 
                        type="file" 
                        accept="image/*"
                    />
                    @error('image') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    @if($image)
                        <flux:text size="sm" class="text-gray-600 mt-1">
                            Imagen seleccionada: {{ $image->getClientOriginalName() }}
                        </flux:text>
                    @endif
                    @if($editingProduct && $editingProduct->getFirstMediaUrl('images'))
                        <div class="mt-2">
                            <flux:text size="sm" class="text-gray-600">
                                Imagen actual: 
                            </flux:text>
                            <img src="{{ $editingProduct->getFirstMediaUrl('images') }}" 
                                 alt="Imagen actual" 
                                 class="mt-1 w-20 h-20 object-cover rounded">
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">
                    {{ __('app.Cancel') }}
                </flux:button>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingProduct ? __('app.Update') : __('app.Create') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-product-modal" :open="$showDeleteModal" wire:model="showDeleteModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Eliminar Producto</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres eliminar este producto?</flux:subheading>
                </div>
            </div>

            @if($productToDelete)
                <div class="bg-gray-50 rounded-lg p-3">
                    <flux:text class="font-medium">{{ $productToDelete->description }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Código: {{ $productToDelete->code }}
                    </flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Precio: ${{ number_format($productToDelete->price, 2) }}
                    </flux:text>
                </div>
            @endif

            <flux:text class="text-gray-600">
                Esta acción eliminará el producto permanentemente y no se puede deshacer.
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="delete({{ $productToDelete->id ?? 0 }})">
                    Eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>