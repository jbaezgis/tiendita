<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

new class extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $roleFilter = '';
    public $departmentFilter = '';

    // Modal states
    public $selectedUser = null;

    // Form fields
    public $form = [
        'name' => '',
        'email' => '',
        'cedula' => '',
        'position' => '',
        'department' => '',
        'phone' => '',
        'password' => '',
        'email_verified_at' => null,
        'category_id' => '',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function updatingDepartmentFilter()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->form = [
            'name' => '',
            'email' => '',
            'cedula' => '',
            'position' => '',
            'department' => '',
            'phone' => '',
            'password' => '12345678', // Default password
            'email_verified_at' => now(),
            'category_id' => '',
        ];
    }

    public function openCreateModal()
    {
        $this->resetForm();
        Flux::modal('create-user-modal')->show();
    }

    public function closeCreateModal()
    {
        Flux::modal('create-user-modal')->close();
        $this->resetForm();
    }

    public function openEditModal($userId)
    {
        $this->selectedUser = User::findOrFail($userId);
        
        $this->form = [
            'name' => $this->selectedUser->name,
            'email' => $this->selectedUser->email,
            'cedula' => $this->selectedUser->cedula,
            'position' => $this->selectedUser->position ?? '',
            'department' => $this->selectedUser->department ?? '',
            'phone' => $this->selectedUser->phone ?? '',
            'password' => '',
            'email_verified_at' => $this->selectedUser->email_verified_at,
            'category_id' => $this->selectedUser->category_id ?? '',
        ];
        
        Flux::modal('edit-user-modal')->show();
    }

    public function closeEditModal()
    {
        Flux::modal('edit-user-modal')->close();
        $this->selectedUser = null;
        $this->resetForm();
    }

    public function openDeleteModal($userId)
    {
        $this->selectedUser = User::findOrFail($userId);
        Flux::modal('delete-user-modal')->show();
    }

    public function closeDeleteModal()
    {
        Flux::modal('delete-user-modal')->close();
        $this->selectedUser = null;
    }

    public function createUser()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.email' => 'required|email|unique:users,email',
            'form.cedula' => 'required|string|unique:users,cedula',
            'form.position' => 'nullable|string|max:255',
            'form.department' => 'nullable|string|max:255',
            'form.phone' => 'nullable|string|max:20',
            'form.password' => 'required|string|min:8',
        ], [
            'form.name.required' => 'El nombre es obligatorio',
            'form.email.required' => 'El email es obligatorio',
            'form.email.email' => 'Debe ser un email válido',
            'form.email.unique' => 'Este email ya está registrado',
            'form.cedula.required' => 'La cédula es obligatoria',
            'form.cedula.unique' => 'Esta cédula ya está registrada',
            'form.password.required' => 'La contraseña es obligatoria',
            'form.password.min' => 'La contraseña debe tener al menos 8 caracteres',
        ]);

        try {
            User::create([
                'name' => $this->form['name'],
                'email' => $this->form['email'],
                'cedula' => $this->form['cedula'],
                'position' => $this->form['position'],
                'department' => $this->form['department'],
                'phone' => $this->form['phone'],
                'password' => Hash::make($this->form['password']),
                'email_verified_at' => $this->form['email_verified_at'],
                'category_id' => $this->form['category_id'] ?: null,
            ]);

            Flux::modal('create-user-modal')->close();
            $this->resetForm();
            
            Flux::toast(
                heading: 'Usuario creado',
                text: 'El usuario ha sido creado exitosamente',
                variant: 'success',
                position: 'top-right'
            );

        } catch (\Exception $e) {
            Flux::toast(
                heading: 'Error',
                text: 'Error al crear el usuario: ' . $e->getMessage(),
                variant: 'error',
                position: 'top-right'
            );
        }
    }

    public function updateUser()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->selectedUser->id)],
            'form.cedula' => ['required', 'string', Rule::unique('users', 'cedula')->ignore($this->selectedUser->id)],
            'form.position' => 'nullable|string|max:255',
            'form.department' => 'nullable|string|max:255',
            'form.phone' => 'nullable|string|max:20',
            'form.password' => 'nullable|string|min:8',
        ], [
            'form.name.required' => 'El nombre es obligatorio',
            'form.email.required' => 'El email es obligatorio',
            'form.email.email' => 'Debe ser un email válido',
            'form.email.unique' => 'Este email ya está registrado',
            'form.cedula.required' => 'La cédula es obligatoria',
            'form.cedula.unique' => 'Esta cédula ya está registrada',
            'form.password.min' => 'La contraseña debe tener al menos 8 caracteres',
        ]);

        try {
            $updateData = [
                'name' => $this->form['name'],
                'email' => $this->form['email'],
                'cedula' => $this->form['cedula'],
                'position' => $this->form['position'],
                'department' => $this->form['department'],
                'phone' => $this->form['phone'],
                'category_id' => $this->form['category_id'] ?: null,
            ];

            if (!empty($this->form['password'])) {
                $updateData['password'] = Hash::make($this->form['password']);
            }

            $this->selectedUser->update($updateData);

            Flux::modal('edit-user-modal')->close();
            $this->selectedUser = null;
            $this->resetForm();
            
            Flux::toast(
                heading: 'Usuario actualizado',
                text: 'El usuario ha sido actualizado exitosamente',
                variant: 'success',
                position: 'top-right'
            );

        } catch (\Exception $e) {
            Flux::toast(
                heading: 'Error',
                text: 'Error al actualizar el usuario: ' . $e->getMessage(),
                variant: 'error',
                position: 'top-right'
            );
        }
    }

    public function deleteUser()
    {
        try {
            if ($this->selectedUser->id === auth()->id()) {
                Flux::toast(
                    heading: 'Error',
                    text: 'No puedes eliminar tu propio usuario',
                    variant: 'error',
                    position: 'top-right'
                );
                return;
            }

            $userName = $this->selectedUser->name;
            $this->selectedUser->delete();
            Flux::modal('delete-user-modal')->close();
            $this->selectedUser = null;
            
            Flux::toast(
                heading: 'Usuario eliminado',
                text: "El usuario '{$userName}' ha sido eliminado exitosamente",
                variant: 'success',
                position: 'top-right'
            );

        } catch (\Exception $e) {
            Flux::toast(
                heading: 'Error',
                text: 'Error al eliminar el usuario: ' . $e->getMessage(),
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

    public function getDepartments()
    {
        return [
            'Administración' => 'Administración',
            'Recursos Humanos' => 'Recursos Humanos',
            'Finanzas' => 'Finanzas',
            'Tecnología' => 'Tecnología',
            'Ventas' => 'Ventas',
            'Marketing' => 'Marketing',
            'Operaciones' => 'Operaciones',
        ];
    }

    public function with(): array
    {
        $query = User::with(['roles', 'employee', 'category'])
            ->orderBy('name');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('cedula', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->departmentFilter) {
            $query->where('department', $this->departmentFilter);
        }

        if ($this->roleFilter) {
            $query->whereHas('roles', function($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        return [
            'users' => $query->paginate(15),
            'roles' => Role::orderBy('name')->get(),
            'departments' => $this->getDepartments(),
            'categories' => \App\Models\Category::orderBy('code')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">{{ __('app.User Management') }}</flux:heading>
            <flux:text class="text-gray-600 mt-1">{{ __('app.System user administration') }}</flux:text>
        </div>
        <div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('app.New User') }}
            </flux:button>
        </div>
    </div>

    <!-- Filtros -->
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('app.Filters') }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">{{ $users->total() }} {{ __('app.user(s) found') }}</flux:text>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                icon="magnifying-glass"
                placeholder="{{ __('app.Search user...') }}" 
            />
            <flux:select wire:model.live="departmentFilter" placeholder="{{ __('app.Department') }}">
                <flux:select.option value="">{{ __('app.All departments') }}</flux:select.option>
                @foreach($departments as $key => $department)
                    <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="roleFilter" placeholder="{{ __('app.Role') }}">
                <flux:select.option value="">{{ __('app.All roles') }}</flux:select.option>
                @foreach($roles as $role)
                    <flux:select.option value="{{ $role->name }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <!-- Tabla de Usuarios -->
    <flux:card>
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>{{ __('app.User') }}</flux:table.column>
                <flux:table.column>{{ __('app.Contact') }}</flux:table.column>
                <flux:table.column>{{ __('app.Position') }}</flux:table.column>
                <flux:table.column>{{ __('app.Department') }}</flux:table.column>
                <flux:table.column>{{ __('app.Category') }}</flux:table.column>
                <flux:table.column>{{ __('app.Roles') }}</flux:table.column>
                <flux:table.column>{{ __('app.Employee') }}</flux:table.column>
                <flux:table.column>{{ __('app.Status') }}</flux:table.column>
                <flux:table.column>{{ __('app.Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <!-- Usuario -->
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <flux:text size="sm" class="font-bold text-blue-600">
                                        {{ $this->getUserInitials($user->name) }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="font-medium">{{ $user->name }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $user->cedula }}</flux:text>
                                </div>
                            </div>
                        </flux:table.cell>

                        <!-- Contacto -->
                        <flux:table.cell>
                            <div>
                                <flux:text size="sm">{{ $user->email }}</flux:text>
                                @if($user->phone)
                                    <flux:text size="sm" class="text-gray-500 block">{{ $user->phone }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        <!-- Cargo -->
                        <flux:table.cell>
                            {{ $user->position ?? '-' }}
                        </flux:table.cell>

                        <!-- Departamento -->
                        <flux:table.cell>
                            @if($user->department)
                                <flux:badge color="gray" size="sm">{{ $user->department }}</flux:badge>
                            @else
                                <flux:text class="text-gray-400">-</flux:text>
                            @endif
                        </flux:table.cell>

                        <!-- Categoría -->
                        <flux:table.cell>
                            @if($user->category)
                                <flux:badge color="blue" size="sm">{{ $user->category->code }}</flux:badge>
                                <flux:text size="xs" class="text-gray-500 block">
                                    Límite: RD$ {{ number_format($user->category->purchase_limit, 2) }}
                                </flux:text>
                            @else
                                <flux:badge color="gray" size="sm">Sin categoría</flux:badge>
                            @endif
                        </flux:table.cell>

                        <!-- Roles -->
                        <flux:table.cell>
                            @if($user->roles->count() > 0)
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <flux:badge color="blue" size="xs">{{ $role->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <flux:text class="text-gray-400">Sin roles</flux:text>
                            @endif
                        </flux:table.cell>

                        <!-- Empleado -->
                        <flux:table.cell>
                            @if($user->employee)
                                <flux:badge color="green" size="sm">{{ $user->employee->code }}</flux:badge>
                            @else
                                <flux:badge color="gray" size="sm">Sin empleado</flux:badge>
                            @endif
                        </flux:table.cell>

                        <!-- Estado -->
                        <flux:table.cell>
                            @if($user->email_verified_at)
                                <flux:badge color="green" size="sm">Verificado</flux:badge>
                            @else
                                <flux:badge color="yellow" size="sm">Pendiente</flux:badge>
                            @endif
                        </flux:table.cell>

                        <!-- Acciones -->
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $user->id }})">
                                        Editar
                                    </flux:menu.item>
                                    <flux:menu.item icon="key" href="{{ route('users.assign-roles', $user->id) }}">
                                        Asignar Roles
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    @if($user->id !== auth()->id())
                                        <flux:menu.item 
                                            icon="trash" 
                                            variant="danger"
                                            wire:click="openDeleteModal({{ $user->id }})"
                                        >
                                            Eliminar
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center py-12">
                            <div class="flex flex-col items-center">
                                <flux:icon.users class="h-12 w-12 text-gray-400 mb-4" />
                                <flux:heading size="lg" class="text-gray-600">No se encontraron usuarios</flux:heading>
                                <flux:text class="text-gray-500 mt-2">Intenta ajustar los filtros o crear un nuevo usuario.</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Modal de Crear Usuario -->
    <flux:modal name="create-user-modal">
        <form wire:submit.prevent="createUser">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Crear Nuevo Usuario</flux:heading>
                    <flux:subheading>Ingresa los datos del nuevo usuario</flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Nombre Completo</flux:label>
                        <flux:input wire:model="form.name" placeholder="Ej: Juan Pérez" />
                        <flux:error name="form.name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Cédula</flux:label>
                        <flux:input wire:model="form.cedula" placeholder="000-0000000-0" />
                        <flux:error name="form.cedula" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input type="email" wire:model="form.email" placeholder="usuario@empresa.com" />
                        <flux:error name="form.email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Teléfono</flux:label>
                        <flux:input wire:model="form.phone" placeholder="(809) 000-0000" />
                        <flux:error name="form.phone" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Cargo</flux:label>
                        <flux:input wire:model="form.position" placeholder="Ej: Supervisor" />
                        <flux:error name="form.position" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Departamento</flux:label>
                        <flux:select wire:model="form.department" placeholder="Seleccionar departamento">
                            @foreach($this->getDepartments() as $key => $department)
                                <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.department" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Categoría</flux:label>
                        <flux:select wire:model="form.category_id" placeholder="Seleccionar categoría">
                            <flux:select.option value="">Sin categoría</flux:select.option>
                            @foreach($categories as $category)
                                <flux:select.option value="{{ $category->id }}">
                                    {{ $category->code }} - Límite: RD$ {{ number_format($category->purchase_limit, 2) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.category_id" />
                    </flux:field>

                    <flux:field class="md:col-span-2">
                        <flux:label>Contraseña</flux:label>
                        <flux:input type="password" wire:model="form.password" placeholder="Mínimo 8 caracteres" />
                        <flux:error name="form.password" />
                        <flux:description>La contraseña por defecto es: 12345678</flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeCreateModal">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Crear Usuario
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <!-- Modal de Editar Usuario -->
    <flux:modal name="edit-user-modal">
        <form wire:submit.prevent="updateUser">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Editar Usuario</flux:heading>
                    <flux:subheading>Modifica los datos del usuario</flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Nombre Completo</flux:label>
                        <flux:input wire:model="form.name" placeholder="Ej: Juan Pérez" />
                        <flux:error name="form.name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Cédula</flux:label>
                        <flux:input wire:model="form.cedula" placeholder="000-0000000-0" />
                        <flux:error name="form.cedula" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input type="email" wire:model="form.email" placeholder="usuario@empresa.com" />
                        <flux:error name="form.email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Teléfono</flux:label>
                        <flux:input wire:model="form.phone" placeholder="(809) 000-0000" />
                        <flux:error name="form.phone" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Cargo</flux:label>
                        <flux:input wire:model="form.position" placeholder="Ej: Supervisor" />
                        <flux:error name="form.position" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Departamento</flux:label>
                        <flux:select wire:model="form.department" placeholder="Seleccionar departamento">
                            @foreach($this->getDepartments() as $key => $department)
                                <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.department" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Categoría</flux:label>
                        <flux:select wire:model="form.category_id" placeholder="Seleccionar categoría">
                            <flux:select.option value="">Sin categoría</flux:select.option>
                            @foreach($categories as $category)
                                <flux:select.option value="{{ $category->id }}">
                                    {{ $category->code }} - Límite: RD$ {{ number_format($category->purchase_limit, 2) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.category_id" />
                    </flux:field>

                    <flux:field class="md:col-span-2">
                        <flux:label>Nueva Contraseña</flux:label>
                        <flux:input type="password" wire:model="form.password" placeholder="Dejar vacío para no cambiar" />
                        <flux:error name="form.password" />
                        <flux:description>Dejar vacío si no deseas cambiar la contraseña</flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeEditModal">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Actualizar Usuario
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <!-- Modal de Eliminar Usuario -->
    <flux:modal name="delete-user-modal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Eliminar Usuario</flux:heading>
                    @if($selectedUser)
                        <flux:subheading>¿Estás seguro de eliminar a {{ $selectedUser->name }}?</flux:subheading>
                    @endif
                </div>
            </div>

            @if($selectedUser)
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <flux:text class="text-gray-800">
                        <strong>Información del usuario:</strong><br>
                        • Nombre: {{ $selectedUser->name }}<br>
                        • Email: {{ $selectedUser->email }}<br>
                        • Cédula: {{ $selectedUser->cedula }}<br>
                        @if($selectedUser->position)
                            • Cargo: {{ $selectedUser->position }}<br>
                        @endif
                        @if($selectedUser->department)
                            • Departamento: {{ $selectedUser->department }}<br>
                        @endif
                        @if($selectedUser->category)
                            • Categoría: {{ $selectedUser->category->code }} (Límite: RD$ {{ number_format($selectedUser->category->purchase_limit, 2) }})<br>
                        @endif
                        @if($selectedUser->employee)
                            • Empleado: {{ $selectedUser->employee->code }}<br>
                        @endif
                    </flux:text>
                </div>
            @endif

            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <flux:text class="text-red-800">
                    <strong>⚠️ Advertencia:</strong> Esta acción no se puede deshacer. El usuario será eliminado permanentemente del sistema.
                    @if($selectedUser && $selectedUser->id === auth()->id())
                        <br><br>
                        <strong class="text-red-900">No puedes eliminar tu propio usuario.</strong>
                    @endif
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteModal">
                    Cancelar
                </flux:button>
                <flux:button 
                    variant="danger" 
                    wire:click="deleteUser"
                    :disabled="$selectedUser && $selectedUser->id === auth()->id()"
                >
                    Eliminar Usuario
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>