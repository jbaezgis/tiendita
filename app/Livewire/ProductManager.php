<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;

class ProductManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingProduct = null;

    #[Validate('required|string')]
    public $code = '';

    #[Validate('required|string')]
    public $description = '';

    #[Validate('required|numeric|min:0')]
    public $price = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openModal()
    {
        $this->reset(['code', 'description', 'price', 'editingProduct']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['code', 'description', 'price', 'editingProduct']);
        $this->resetValidation();
    }

    public function save()
    {
        $validated = $this->validate([
            'code' => 'required|string|unique:products,code' . ($this->editingProduct ? ',' . $this->editingProduct->id : ''),
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
        ]);

        if ($this->editingProduct) {
            $this->editingProduct->update($validated);
            session()->flash('message', 'Producto actualizado exitosamente.');
        } else {
            Product::create($validated);
            session()->flash('message', 'Producto creado exitosamente.');
        }

        $this->closeModal();
    }

    public function edit(Product $product)
    {
        $this->editingProduct = $product;
        $this->code = $product->code;
        $this->description = $product->description;
        $this->price = $product->price;
        $this->showModal = true;
    }

    public function delete(Product $product)
    {
        $product->delete();
        session()->flash('message', 'Producto eliminado exitosamente.');
    }

    public function render()
    {
        $products = Product::when($this->search, function ($query) {
            $query->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
        })->paginate(10);

        return view('livewire.product-manager', compact('products'));
    }
}
