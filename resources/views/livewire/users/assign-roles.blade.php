<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component
{
    public $user;
    public $selectedRoles = [];
    public $currentRoles = [];

    public function mount($id)
    {
        $this->user = User::with('roles')->findOrFail($id);
        $this->currentRoles = $this->user->roles->pluck('name')->toArray();
        $this->selectedRoles = $this->currentRoles;
    }

    public function assignRoles()
    {
        try {
            $this->user->syncRoles($this->selectedRoles);

            Flux::toast(
                heading: 'Roles actualizados',
                text: 'Los roles han sido asignados exitosamente',
                variant: 'success',
                position: 'top-right'
            );

            return $this->redirect(route('users.index'));

        } catch (\Exception $e) {
            Flux::toast(
                heading: 'Error',
                text: 'Error al asignar roles: ' . $e->getMessage(),
                variant: 'error',
                position: 'top-right'
            );
        }
    }

    public function getUserInitials($name)
    {
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    public function with(): array
    {
        return [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('users.index') }}" variant="ghost" icon="arrow-left" size="sm">
            Volver
        </flux:button>
        <div>
            <flux:heading size="xl">Asignar Roles</flux:heading>
            <flux:text class="text-gray-600 mt-1">Gestionar roles para {{ $user->name }}</flux:text>
        </div>
    </div>

    <!-- Usuario Info -->
    <flux:card>
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                <flux:text size="lg" class="font-bold text-blue-600">
                    {{ $this->getUserInitials($user->name) }}
                </flux:text>
            </div>
            <div class="flex-1">
                <flux:heading size="lg">{{ $user->name }}</flux:heading>
                <flux:text class="text-gray-600">{{ $user->email }}</flux:text>
                <div class="flex items-center gap-4 mt-2">
                    @if($user->cedula)
                        <flux:text size="sm" class="text-gray-500">Cédula: {{ $user->cedula }}</flux:text>
                    @endif
                    @if($user->department)
                        <flux:badge color="gray" size="sm">{{ $user->department }}</flux:badge>
                    @endif
                    @if($user->position)
                        <flux:badge color="blue" size="sm">{{ $user->position }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Asignación de Roles -->
    <flux:card>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Asignar Roles</flux:heading>
                <flux:subheading>Selecciona los roles que tendrá este usuario</flux:subheading>
            </div>

            <form wire:submit.prevent="assignRoles">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse($roles as $role)
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <flux:checkbox 
                                        wire:model="selectedRoles" 
                                        value="{{ $role->name }}"
                                        class="mt-1"
                                    />
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ $role->name }}</flux:text>
                                            @if(in_array($role->name, $currentRoles))
                                                <flux:badge color="green" size="xs">Actual</flux:badge>
                                            @endif
                                        </div>
                                        @if($role->permissions->count() > 0)
                                            <flux:text size="sm" class="text-gray-500 mt-1">
                                                {{ $role->permissions->count() }} permiso(s)
                                            </flux:text>
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach($role->permissions->take(3) as $permission)
                                                    <flux:badge color="gray" size="xs">{{ $permission->name }}</flux:badge>
                                                @endforeach
                                                @if($role->permissions->count() > 3)
                                                    <flux:badge color="gray" size="xs">+{{ $role->permissions->count() - 3 }} más</flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-8">
                                <flux:text class="text-gray-500">No hay roles disponibles</flux:text>
                            </div>
                        @endforelse
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <div>
                            <flux:text size="sm" class="text-gray-600">
                                Roles seleccionados: {{ count($selectedRoles) }}
                            </flux:text>
                        </div>
                        <div class="flex gap-3">
                            <flux:button type="button" variant="ghost" href="{{ route('users.index') }}">
                                Cancelar
                            </flux:button>
                            <flux:button type="submit" variant="primary">
                                Guardar Roles
                            </flux:button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </flux:card>

    <!-- Vista previa de cambios -->
    @if(count(array_diff($selectedRoles, $currentRoles)) > 0 || count(array_diff($currentRoles, $selectedRoles)) > 0)
        <flux:card>
            <div class="space-y-4">
                <flux:heading size="lg">Vista previa de cambios</flux:heading>
                
                @if(count(array_diff($selectedRoles, $currentRoles)) > 0)
                    <div>
                        <flux:text class="font-medium text-green-800">Roles que se agregarán:</flux:text>
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach(array_diff($selectedRoles, $currentRoles) as $newRole)
                                <flux:badge color="green" size="sm">{{ $newRole }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count(array_diff($currentRoles, $selectedRoles)) > 0)
                    <div>
                        <flux:text class="font-medium text-red-800">Roles que se removerán:</flux:text>
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach(array_diff($currentRoles, $selectedRoles) as $removedRole)
                                <flux:badge color="red" size="sm">{{ $removedRole }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>
    @endif
</div>