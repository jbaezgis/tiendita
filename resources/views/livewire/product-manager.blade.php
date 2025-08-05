<div>
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-700 rounded">
            {{ session('message') }}
        </div>
    @endif

    <div class="mb-6 flex justify-between items-center">
        <div class="flex-1 max-w-md">
            <flux:input 
                wire:model.live="search" 
                placeholder="Buscar productos..." 
                type="search"
            />
        </div>
        <flux:button wire:click="openModal" variant="primary">
            Nuevo Producto
        </flux:button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Código
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Descripción
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Precio
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($products as $product)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $product->code }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ $product->description }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            ${{ number_format($product->price, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2">
                                <flux:button 
                                    wire:click="edit({{ $product->id }})" 
                                    size="sm"
                                    variant="ghost"
                                >
                                    Editar
                                </flux:button>
                                <flux:button 
                                    wire:click="delete({{ $product->id }})" 
                                    wire:confirm="¿Estás seguro de eliminar este producto?"
                                    size="sm"
                                    variant="danger"
                                >
                                    Eliminar
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No se encontraron productos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                    {{ $editingProduct ? 'Editar Producto' : 'Nuevo Producto' }}
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <flux:input 
                                            wire:model="code" 
                                            label="Código" 
                                            placeholder="Código del producto"
                                        />
                                        @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <flux:textarea 
                                            wire:model="description" 
                                            label="Descripción" 
                                            placeholder="Descripción del producto"
                                            rows="3"
                                        />
                                        @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <flux:input 
                                            wire:model="price" 
                                            label="Precio" 
                                            type="number" 
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                        />
                                        @error('price') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <flux:button wire:click="save" variant="primary" class="w-full sm:w-auto sm:ml-3">
                            {{ $editingProduct ? 'Actualizar' : 'Crear' }}
                        </flux:button>
                        <flux:button wire:click="closeModal" variant="ghost" class="mt-3 w-full sm:mt-0 sm:w-auto">
                            Cancelar
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
