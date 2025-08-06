<?php

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Flux\Flux;
use Barryvdh\DomPDF\Facade\Pdf;

new #[Layout('components.layouts.app')] class extends Component {
    public Order $order;
    
    // Modal states
    public $showApprovalModal = false;
    public $showRejectionModal = false;
    public $rejectionReason = '';

    public function mount($id)
    {
        $this->order = Order::with(['employee', 'category', 'items.product', 'approver', 'creator'])
            ->findOrFail($id);
    }

    public function exportPdf()
    {
        $order = $this->order->load(['employee', 'category', 'items.product', 'approver', 'creator']);
        
        // Convertir logo a base64
        $logoPath = public_path('images/logo.png');
        $logoBase64 = null;
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        
        // Calcular número total de páginas basado en el contenido
        $totalPages = 1; // Página principal siempre existe
        
        // Si hay información de aprobación, se agrega una página adicional
        if ($order->approver) {
            $totalPages++;
        }
        
        // Si hay muchas notas o el pedido es muy largo, podría necesitar más páginas
        // Por ahora mantenemos la lógica simple, pero se puede expandir
        
        // Calcular número total de páginas basado en el contenido
        $totalPages = 1; // Página principal siempre existe
        
        // Si hay información de aprobación, se agrega una página adicional
        if ($order->approver) {
            $totalPages++;
        }
        
        $pdf = Pdf::loadView('pdf.order', compact('order', 'logoBase64', 'totalPages'));
        $pdf->getDomPDF()->set_option('enable_php', true);
        $pdf->getDomPDF()->set_option('isPhpEnabled', true);
        
        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'pedido-' . $order->order_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function openApprovalModal()
    {
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
    }

    public function openRejectionModal()
    {
        $this->rejectionReason = '';
        $this->showRejectionModal = true;
    }

    public function closeRejectionModal()
    {
        $this->showRejectionModal = false;
        $this->rejectionReason = '';
    }

    public function approveOrder()
    {
        if (!$this->order->canBeApproved()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede aprobar este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->order->approve(auth()->user());
        $this->order = $this->order->fresh(['employee', 'category', 'items.product', 'approver', 'creator']);

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

        if (!$this->order->canBeRejected()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede rechazar este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->order->reject(auth()->user(), $this->rejectionReason);
        $this->order = $this->order->fresh(['employee', 'category', 'items.product', 'approver', 'creator']);

        Flux::toast(
            heading: 'Pedido rechazado',
            text: 'El pedido ha sido rechazado',
            variant: 'success',
            position: 'top-right'
        );

        $this->closeRejectionModal();
    }

    public function deliverOrder()
    {
        if (!$this->order->canBeDelivered()) {
            Flux::toast(
                heading: 'Error',
                text: 'No se puede marcar como entregado este pedido',
                variant: 'error',
                position: 'top-right'
            );
            return;
        }

        $this->order->deliver();
        $this->order = $this->order->fresh(['employee', 'category', 'items.product', 'approver', 'creator']);

        Flux::toast(
            heading: 'Pedido entregado',
            text: 'El pedido ha sido marcado como entregado',
            variant: 'success',
            position: 'top-right'
        );
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">Detalle del Pedido {{ $order->order_number }}</flux:heading>
            <flux:subheading>Información completa del pedido</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="document-arrow-down" wire:click="exportPdf" variant="outline" size="sm">
                Exportar PDF
            </flux:button>
            <flux:button icon="arrow-left" href="{{ route('orders.index') }}" variant="ghost" size="sm">
                Volver a Pedidos
            </flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-6"/>

    <!-- Order Header Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <flux:icon.user class="h-6 w-6 text-blue-600" />
                </div>
                <div>
                    <flux:text size="sm" class="text-gray-600">Empleado</flux:text>
                    <flux:text class="font-bold">{{ $order->employee->name }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">{{ $order->employee->department }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.currency-dollar class="h-6 w-6 text-green-600" />
                </div>
                <div>
                    <flux:text size="sm" class="text-gray-600">Total del Pedido</flux:text>
                    <flux:text class="font-bold text-2xl">${{ number_format($order->total, 2) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">{{ $order->items->count() }} productos</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <flux:icon.clock class="h-6 w-6 text-yellow-600" />
                </div>
                <div>
                    <flux:text size="sm" class="text-gray-600">Estado</flux:text>
                    <flux:badge :color="$order->getStatusColor()" size="lg">
                        {{ $order::getStatusOptions()[$order->status] }}
                    </flux:badge>
                    {{-- <flux:text size="sm" class="text-gray-500">{{ $order->created_at->format('d/m/Y') }}</flux:text> --}}
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Order Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Items -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Productos del Pedido</flux:heading>
                    <flux:text size="sm" class="text-gray-500">{{ $order->getTotalQuantity() }} unidades</flux:text>
                </div>
                
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Unit.</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                                <flux:icon.cube class="h-5 w-5 text-gray-600" />
                                            </div>
                                            <div>
                                                <flux:text class="font-medium">{{ $item->product->description }}</flux:text>
                                                <flux:text size="sm" class="text-gray-500">{{ $item->product->code }}</flux:text>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.cube-transparent class="h-4 w-4 text-gray-500" />
                                            <flux:text>{{ $item->quantity }}</flux:text>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <flux:text>${{ number_format($item->price, 2) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-4">
                                        <flux:text class="font-medium">${{ number_format($item->subtotal, 2) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-4">
                                        <flux:badge :color="$item->getStatusColor()" size="sm">
                                            {{ ucfirst($item->status) }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-medium">Total:</td>
                                <td class="px-4 py-3 font-bold text-lg">${{ number_format($order->total, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </flux:card>

            <!-- Notes -->
            @if($order->notes)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Notas del Pedido</flux:heading>
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <flux:text>{{ $order->notes }}</flux:text>
                    </div>
                </flux:card>
            @endif

            <!-- Rejection Reason -->
            @if($order->status === 'rejected' && $order->rejection_reason)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Razón del Rechazo</flux:heading>
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <flux:text>{{ $order->rejection_reason }}</flux:text>
                    </div>
                </flux:card>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Order Info -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Información del Pedido</flux:heading>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <flux:text class="text-gray-600">Número:</flux:text>
                        <flux:text class="font-medium">{{ $order->order_number }}</flux:text>
                    </div>
                    
                    <div class="flex justify-between">
                        <flux:text class="text-gray-600">Fecha:</flux:text>
                        <flux:text class="font-medium">{{ $order->order_date->format('d/m/Y') }}</flux:text>
                    </div>
                    
                    <div class="flex justify-between">
                        <flux:text class="text-gray-600">Prioridad:</flux:text>
                        <flux:badge :color="$order->getPriorityColor()" size="sm">
                            {{ $order::getPriorityOptions()[$order->priority] }}
                        </flux:badge>
                    </div>
                    
                    @if($order->category)
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Categoría:</flux:text>
                            <flux:text class="font-medium">{{ $order->category->code }}</flux:text>
                        </div>
                        
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Límite de compra:</flux:text>
                            <flux:text class="font-medium">${{ number_format($order->category->purchase_limit, 2) }}</flux:text>
                        </div>
                        
                        @if(!$order->isWithinPurchaseLimit())
                            <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <flux:icon.exclamation-triangle class="h-4 w-4 text-orange-600" />
                                    <flux:text size="sm" class="text-orange-800">
                                        Este pedido excede el límite de compra
                                    </flux:text>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </flux:card>

            <!-- Approval Info -->
            @if($order->approver)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Información de Aprobación</flux:heading>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Aprobado por:</flux:text>
                            <flux:text class="font-medium">{{ $order->approver->name }}</flux:text>
                        </div>
                        
                        <div class="flex justify-between">
                            <flux:text class="text-gray-600">Fecha de aprobación:</flux:text>
                            <flux:text class="font-medium">{{ $order->approved_at->format('d/m/Y H:i') }}</flux:text>
                        </div>
                    </div>
                </flux:card>
            @endif

            <!-- Creator Info -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Información de Creación</flux:heading>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <flux:text class="text-gray-600">Creado por:</flux:text>
                        <flux:text class="font-medium">{{ $order->creator->name }}</flux:text>
                    </div>
                    
                    <div class="flex justify-between">
                        <flux:text class="text-gray-600">Fecha de creación:</flux:text>
                        <flux:text class="font-medium">{{ $order->created_at->format('d/m/Y H:i') }}</flux:text>
                    </div>
                </div>
            </flux:card>

            <!-- Actions -->
            @if($order->canBeApproved() || $order->canBeRejected() || $order->canBeDelivered())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Acciones</flux:heading>
                    
                    <div class="space-y-2">
                        @if($order->canBeApproved())
                            <flux:button variant="primary" class="w-full" icon="check" wire:click="openApprovalModal">
                                Aprobar Pedido
                            </flux:button>
                        @endif
                        
                        @if($order->canBeRejected())
                            <flux:button variant="danger" class="w-full" icon="x-mark" wire:click="openRejectionModal">
                                Rechazar Pedido
                            </flux:button>
                        @endif
                        
                        @if($order->canBeDelivered())
                            <flux:button variant="outline" class="w-full" icon="truck" wire:click="deliverOrder">
                                Marcar como Entregado
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <!-- Modal de Aprobación -->
    <flux:modal name="approval-modal" :open="$showApprovalModal" wire:model="showApprovalModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <flux:icon.check class="h-5 w-5 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">Aprobar Pedido</flux:heading>
                    <flux:subheading>¿Aprobar el pedido {{ $order->order_number }}?</flux:subheading>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <flux:text class="font-medium">Empleado:</flux:text>
                        <flux:text>{{ $order->employee->name }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium">Total:</flux:text>
                        <flux:text>${{ number_format($order->total, 2) }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium">Items:</flux:text>
                        <flux:text>{{ $order->items->count() }} productos</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium">Prioridad:</flux:text>
                        <flux:badge :color="$order->getPriorityColor()" size="sm">
                            {{ $order::getPriorityOptions()[$order->priority] }}
                        </flux:badge>
                    </div>
                </div>
            </div>

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
                    <flux:subheading>Rechazar el pedido {{ $order->order_number }}</flux:subheading>
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