<?php

use App\Models\Employee;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Histórico de integrantes inactivos.
 *
 * Nadie se borra del padrón: quien deja de estar activo en Cultiva pasa aquí
 * con su usuario y su historial de pedidos intactos, pero sin acceso a la
 * tienda. La sincronización horaria es quien los mueve.
 */
new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $departmentFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function getIntegrantesProperty()
    {
        return Employee::with(['company', 'user'])
            ->where('active', false)
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('code', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%")
                        ->orWhere('cedula', 'like', "%{$this->search}%")
                        ->orWhere('position', 'like', "%{$this->search}%");
                });
            })
            ->when($this->departmentFilter, fn ($q) => $q->where('department', $this->departmentFilter))
            ->orderBy('name')
            ->paginate(15);
    }

    public function getUserInitials(string $name): string
    {
        $words = explode(' ', $name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    public function with(): array
    {
        return [
            'departments' => Employee::where('active', false)
                ->whereNotNull('department')
                ->distinct()
                ->orderBy('department')
                ->pluck('department'),
        ];
    }
}; ?>

<div>
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">Histórico de integrantes</flux:heading>
            <flux:text class="mt-1">
                Personal que ya no figura activo. Se conserva porque su historial de pedidos
                sigue asociado a su cuenta, pero no puede acceder a la tienda.
            </flux:text>
        </div>
        <flux:button size="sm" icon="users" :href="route('employees.index')" wire:navigate>
            Integrantes activos
        </flux:button>
    </div>

    <flux:separator class="mt-4 mb-1" />

    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Filtros</flux:heading>
            <flux:text size="sm" class="text-gray-500">
                {{ $this->integrantes->total() }} {{ $this->integrantes->total() === 1 ? 'integrante' : 'integrantes' }}
            </flux:text>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Buscar por código, nombre, cédula o posición..." label="Buscar" />
            </div>
            <flux:select wire:model.live="departmentFilter" placeholder="Departamento" label="Departamento">
                <flux:select.option value="">Todos los departamentos</flux:select.option>
                @foreach ($departments as $department)
                    <flux:select.option value="{{ $department }}">{{ $department }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <flux:table :paginate="$this->integrantes">
        <flux:table.columns>
            <flux:table.column>Código</flux:table.column>
            <flux:table.column>Integrante</flux:table.column>
            <flux:table.column>Posición</flux:table.column>
            <flux:table.column>Empresa</flux:table.column>
            <flux:table.column>Usuario</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->integrantes as $integrante)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:badge variant="pill" color="zinc" size="sm">{{ $integrante->code }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-zinc-100 rounded-full flex items-center justify-center">
                                <flux:text size="sm" class="font-bold text-zinc-500">
                                    {{ $this->getUserInitials($integrante->name) }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $integrante->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $integrante->cedula }}</flux:text>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:text size="sm">{{ $integrante->position }}</flux:text>
                        <flux:text size="sm" class="text-gray-500">{{ $integrante->department }}</flux:text>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:text size="sm">{{ $integrante->company?->name ?? 'Sin empresa' }}</flux:text>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($integrante->user)
                            <flux:badge size="sm" color="red">Acceso desactivado</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Sin usuario</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-sm text-gray-500">
                        No hay integrantes en el histórico
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
