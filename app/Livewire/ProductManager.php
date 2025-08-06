<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class ProductManager extends Component
{
    use WithPagination, WithFileUploads;

    public $search = '';
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
    public $image;

    public function updatingSearch()
    {
        $this->resetPage();
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
            session()->flash('message', 'Producto actualizado exitosamente.');
        } else {
            $product = Product::create($productData);
            session()->flash('message', 'Producto creado exitosamente.');
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
        session()->flash('message', 'Producto eliminado exitosamente.');
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

    public function render()
    {
        $products = Product::when($this->search, function ($query) {
            $query->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
        })->paginate(10);

        return view('livewire.product-manager', compact('products'));
    }
}
