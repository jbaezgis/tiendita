<?php

use App\Models\ProductCategory;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Flux\Flux;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public $search = '';
    public $perPage = 10;
    public $sortBy = 'id';
    public $sortDirection = 'desc';
    public $showModal = false;
    public $showDeleteModal = false;
    public $editingCategory = null;
    public $categoryToDelete = null;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('nullable|string')]
    public $description = '';

    #[Validate('boolean')]
    public $is_active = true;

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
        $this->reset(['name', 'description', 'is_active', 'editingCategory']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['name', 'description', 'is_active', 'editingCategory']);
        $this->resetValidation();
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:product_categories,name' . ($this->editingCategory ? ',' . $this->editingCategory->id : ''),
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($this->editingCategory) {
            $this->editingCategory->update($validated);
            Flux::toast(
                heading: 'Categoría actualizada',
                text: 'La categoría ha sido actualizada exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        } else {
            ProductCategory::create($validated);
            Flux::toast(
                heading: 'Categoría creada',
                text: 'La categoría ha sido creada exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        }

        $this->closeModal();
    }

    public function edit(ProductCategory $category)
    {
        $this->editingCategory = $category;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->is_active = $category->is_active;
        $this->showModal = true;
    }

    public function delete(ProductCategory $category)
    {
        // Verificar si hay productos asociados
        if ($category->products()->count() > 0) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede eliminar la categoría porque tiene productos asociados.',
                variant: 'danger',
                position: 'top-right',
            );
            return;
        }

        $category->delete();
        Flux::toast(
            heading: 'Categoría eliminada',
            text: 'La categoría ha sido eliminada exitosamente.',
            variant: 'success',
            position: 'top-right',
        );
        $this->showDeleteModal = false;
        $this->categoryToDelete = null;
    }

    public function openDeleteModal(ProductCategory $category)
    {
        $this->categoryToDelete = $category;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->categoryToDelete = null;
    }

    public function toggleStatus(ProductCategory $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        Flux::toast(
            heading: 'Estado actualizado',
            text: 'El estado de la categoría ha sido actualizado.',
            variant: 'success',
            position: 'top-right',
        );
    }

    public function getCategoriesProperty()
    {
        $query = ProductCategory::query();
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%");
        });
        
        return $query->paginate($this->perPage);
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">Categorías de Productos</flux:heading>
            <flux:subheading>Gestiona las categorías de los productos</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" wire:click="openModal" variant="primary" size="sm">Agregar Categoría</flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-1"/>
    
    <div class="flex justify-end gap-2">
        <div class="flex items-center gap-1 text-xs">
            <flux:text>{{ __('app.Showing') }}</flux:text>
            <flux:text variant="strong">{{ $this->categories->firstItem() }}</flux:text>
            <flux:text>{{ __('app.of') }}</flux:text>
            <flux:text variant="strong">{{ $this->categories->lastItem() }}</flux:text>
            <flux:text>{{ __('app.of') }}</flux:text>
            <flux:text variant="strong">{{ $this->categories->total() }}</flux:text>
            <flux:text>{{ __('app.entries') }}</flux:text>
        </div>
    </div>

    <div class="py-4 flex gap-4">
        <div class="flex-1">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('app.Search') }}..." label="{{ __('app.Search') }}"/>
        </div>
    </div>

    <flux:table :paginate="$this->categories">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">ID</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nombre</flux:table.column>
            <flux:table.column>Descripción</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortDirection" wire:click="sort('is_active')">Estado</flux:table.column>
            <flux:table.column>Productos</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->categories as $category)
                <flux:table.row>
                    <flux:table.cell>{{ $category->id }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:text variant="strong">{{ $category->name }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($category->description)
                            <flux:text size="sm" class="text-gray-600">{{ Str::limit($category->description, 50) }}</flux:text>
                        @else
                            <flux:text size="sm" class="text-gray-400">Sin descripción</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :variant="$category->is_active ? 'success' : 'danger'">
                            {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge variant="info">{{ $category->products_count ?? $category->products()->count() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button size="sm" icon="pencil" wire:click="edit({{ $category->id }})" />
                            <flux:button 
                                size="sm" 
                                :icon="$category->is_active ? 'eye-slash' : 'eye'" 
                                :color="$category->is_active ? 'zinc' : 'green'"
                                variant="primary"
                                wire:click="toggleStatus({{ $category->id }})" 
                            />
                            <flux:button size="sm" icon="trash" variant="danger" wire:click="openDeleteModal({{ $category->id }})" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal -->
    <flux:modal name="category-modal" :open="$showModal" wire:model="showModal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategory ? 'Editar Categoría' : 'Agregar Categoría' }}</flux:heading>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:input 
                        wire:model="name" 
                        label="Nombre" 
                        placeholder="Ej: Champú, Cuadernos, etc."
                    />
                    @error('name') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div>
                    <flux:textarea 
                        wire:model="description" 
                        label="Descripción" 
                        placeholder="Descripción opcional de la categoría"
                        rows="3"
                    />
                    @error('description') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div>
                    <flux:checkbox 
                        wire:model="is_active" 
                        label="Categoría activa"
                    />
                    @error('is_active') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">
                    Cancelar
                </flux:button>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingCategory ? 'Actualizar' : 'Crear' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-category-modal" :open="$showDeleteModal" wire:model="showDeleteModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Eliminar Categoría</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres eliminar esta categoría?</flux:subheading>
                </div>
            </div>

            @if($categoryToDelete)
                <div class="bg-gray-50 rounded-lg p-3">
                    <flux:text class="font-medium">{{ $categoryToDelete->name }}</flux:text>
                    @if($categoryToDelete->description)
                        <flux:text size="sm" class="text-gray-600">
                            {{ $categoryToDelete->description }}
                        </flux:text>
                    @endif
                    <flux:text size="sm" class="text-gray-600">
                        Productos asociados: {{ $categoryToDelete->products()->count() }}
                    </flux:text>
                </div>
            @endif

            <flux:text class="text-gray-600">
                Esta acción eliminará la categoría permanentemente. Solo se pueden eliminar categorías sin productos asociados.
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="delete({{ $categoryToDelete->id ?? 0 }})">
                    Eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div> 