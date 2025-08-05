<?php

use App\Models\Category;
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
    public $editingCategory = null;
    public $categoryToDelete = null;

    #[Validate('required|string')]
    public $code = '';

    #[Validate('required|numeric|min:0')]
    public $salary_from = '';

    #[Validate('required|numeric|min:0')]
    public $salary_to = '';

    #[Validate('required|numeric|min:0')]
    public $purchase_limit = '';

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
        $this->reset(['code', 'salary_from', 'salary_to', 'purchase_limit', 'editingCategory']);
        $this->resetValidation();
        Flux::modal('category-modal')->show();
    }

    public function closeModal()
    {
        Flux::modal('category-modal')->close();
        $this->reset(['code', 'salary_from', 'salary_to', 'purchase_limit', 'editingCategory']);
        $this->resetValidation();
    }

    public function save()
    {
        $validated = $this->validate([
            'code' => 'required|string|unique:categories,code' . ($this->editingCategory ? ',' . $this->editingCategory->id : ''),
            'salary_from' => 'required|numeric|min:0',
            'salary_to' => 'required|numeric|min:0|gte:salary_from',
            'purchase_limit' => 'required|numeric|min:0',
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
            Category::create($validated);
            Flux::toast(
                heading: 'Categoría creada',
                text: 'La categoría ha sido creada exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        }

        $this->closeModal();
    }

    public function edit(Category $category)
    {
        $this->editingCategory = $category;
        $this->code = $category->code;
        $this->salary_from = $category->salary_from;
        $this->salary_to = $category->salary_to;
        $this->purchase_limit = $category->purchase_limit;
        Flux::modal('category-modal')->show();
    }

    public function confirmDelete(Category $category)
    {
        $this->categoryToDelete = $category;
        Flux::modal('delete-category-modal')->show();
    }

    public function deleteCategory()
    {
        if ($this->categoryToDelete) {
            $categoryName = $this->categoryToDelete->code;
            $this->categoryToDelete->delete();
            
            Flux::toast(
                heading: 'Categoría eliminada',
                text: "La categoría '{$categoryName}' ha sido eliminada exitosamente.",
                variant: 'success',
                position: 'top-right',
            );
        }
        
        Flux::modal('delete-category-modal')->close();
        $this->categoryToDelete = null;
    }

    public function closeDeleteModal()
    {
        Flux::modal('delete-category-modal')->close();
        $this->categoryToDelete = null;
    }

    public function getCategoriesProperty()
    {
        $query = Category::query();
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('code', 'like', "%{$this->search}%");
        });
        
        return $query->paginate($this->perPage);
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">{{ __('app.Categories') }}</flux:heading>
            <flux:subheading>{{ __('app.Salary categories and purchase limits management') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" wire:click="openModal" variant="primary" size="sm">{{ __('app.Add Category') }}</flux:button>
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
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="{{ __('app.Search categories...') }}" label="{{ __('app.Search') }}"/>
        </div>
    </div>

    <flux:table :paginate="$this->categories">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">{{ __('app.Code') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'salary_from'" :direction="$sortDirection" wire:click="sort('salary_from')">{{ __('app.Salary From') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'salary_to'" :direction="$sortDirection" wire:click="sort('salary_to')">{{ __('app.Salary To') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'purchase_limit'" :direction="$sortDirection" wire:click="sort('purchase_limit')">{{ __('app.Purchase Limit') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->categories as $item)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:badge variant="pill" color="blue" size="sm">{{ $item->code }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon.currency-dollar class="w-4 h-4 text-green-600" />
                            <span class="font-medium">${{ number_format($item->salary_from, 2) }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon.currency-dollar class="w-4 h-4 text-green-600" />
                            <span class="font-medium">${{ number_format($item->salary_to, 2) }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon.credit-card class="w-4 h-4 text-blue-600" />
                            <span class="font-medium">${{ number_format($item->purchase_limit, 2) }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button size="sm" icon="pencil" wire:click="edit({{ $item->id }})" />
                            <flux:button size="sm" icon="trash" variant="danger" wire:click="confirmDelete({{ $item->id }})" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal -->
    <flux:modal name="category-modal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategory ? __('app.Edit Category') : __('app.Add Category') }}</flux:heading>
                <flux:subheading>{{ $editingCategory ? __('app.Modify category data') : __('app.Enter new category data') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:input 
                        wire:model="code" 
                        label="{{ __('app.Code') }}" 
                        placeholder="{{ __('app.Example: CAT001') }}"
                        icon="hashtag"
                    />
                    @error('code') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input 
                            wire:model="salary_from" 
                            label="{{ __('app.Salary From') }}" 
                            type="number" 
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            icon="currency-dollar"
                        />
                        @error('salary_from') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                    
                    <div>
                        <flux:input 
                            wire:model="salary_to" 
                            label="{{ __('app.Salary To') }}" 
                            type="number" 
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            icon="currency-dollar"
                        />
                        @error('salary_to') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>
                
                <div>
                    <flux:input 
                        wire:model="purchase_limit" 
                        label="{{ __('app.Purchase Limit') }}" 
                        type="number" 
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        icon="credit-card"
                    />
                    @error('purchase_limit') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            <flux:separator />

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">
                    {{ __('app.Cancel') }}
                </flux:button>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingCategory ? __('app.Update') : __('app.Create') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-category-modal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">¿Eliminar categoría?</flux:heading>
                    @if($categoryToDelete)
                        <flux:subheading>¿Estás seguro de eliminar la categoría '{{ $categoryToDelete->code }}'?</flux:subheading>
                    @endif
                </div>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <flux:text class="text-red-800">
                    Esta acción no se puede deshacer. La categoría será eliminada permanentemente del sistema.
                    @if($categoryToDelete)
                        <br><br>
                        <strong>Detalles de la categoría:</strong><br>
                        • Código: {{ $categoryToDelete->code }}<br>
                        • Rango salarial: RD$ {{ number_format($categoryToDelete->salary_from, 2) }} - RD$ {{ number_format($categoryToDelete->salary_to, 2) }}<br>
                        • Límite de compra: RD$ {{ number_format($categoryToDelete->purchase_limit, 2) }}
                    @endif
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="deleteCategory">
                    Eliminar Categoría
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>