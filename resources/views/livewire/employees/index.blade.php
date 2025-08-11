<?php

use App\Models\Employee;
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
    public $departmentFilter = '';
    public $statusFilter = '';
    public $perPage = 10;
    public $sortBy = 'id';
    public $sortDirection = 'desc';
    public $showModal = false;
    public $showDeleteModal = false;
    public $editingEmployee = null;
    public $employeeToDelete = null;

    #[Validate('required|string')]
    public $code = '';

    #[Validate('required|string')]
    public $name = '';

    #[Validate('required|string')]
    public $cedula = '';

    #[Validate('required|string')]
    public $position = '';

    #[Validate('required|string')]
    public $department = '';

    public $category_id = '';
    public $active = true;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
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
        $this->reset(['code', 'name', 'cedula', 'position', 'department', 'category_id', 'active', 'editingEmployee']);
        $this->active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['code', 'name', 'cedula', 'position', 'department', 'category_id', 'active', 'editingEmployee']);
        $this->resetValidation();
    }

    public function save()
    {
        $validated = $this->validate([
            'code' => 'required|string|unique:employees,code' . ($this->editingEmployee ? ',' . $this->editingEmployee->id : ''),
            'name' => 'required|string',
            'cedula' => 'required|string|unique:employees,cedula' . ($this->editingEmployee ? ',' . $this->editingEmployee->id : ''),
            'position' => 'required|string',
            'department' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $validated['active'] = $this->active;
        $validated['company_id'] = 1; // Hardcoded for now

        if ($this->editingEmployee) {
            $this->editingEmployee->update($validated);
            Flux::toast(
                heading: 'Empleado actualizado',
                text: 'El empleado ha sido actualizado exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        } else {
            $employee = Employee::create($validated);
            
            // Forzar sincronización manual después de crear el empleado
            try {
                $employee->syncUser();
                Flux::toast(
                    heading: 'Empleado creado',
                    text: 'El empleado ha sido creado exitosamente con usuario automático.',
                    variant: 'success',
                    position: 'top-right',
                );
            } catch (\Exception $e) {
                Flux::toast(
                    heading: 'Empleado creado',
                    text: 'El empleado ha sido creado exitosamente, pero hubo un problema al crear el usuario.',
                    variant: 'warning',
                    position: 'top-right',
                );
            }
        }

        $this->closeModal();
    }

    public function edit(Employee $employee)
    {
        $this->editingEmployee = $employee;
        $this->code = $employee->code;
        $this->name = $employee->name;
        $this->cedula = $employee->cedula;
        $this->position = $employee->position;
        $this->department = $employee->department;
        $this->category_id = $employee->category_id;
        $this->active = $employee->active;
        $this->showModal = true;
    }

    public function delete(Employee $employee)
    {
        $employee->delete();
        Flux::toast(
            heading: 'Empleado eliminado',
            text: 'El empleado ha sido eliminado exitosamente.',
            variant: 'success',
            position: 'top-right',
        );
        $this->showDeleteModal = false;
        $this->employeeToDelete = null;
    }

    public function openDeleteModal(Employee $employee)
    {
        $this->employeeToDelete = $employee;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->employeeToDelete = null;
    }

    public function toggleStatus(Employee $employee)
    {
        $employee->update(['active' => !$employee->active]);
        
        Flux::toast(
            heading: $employee->active ? 'Empleado activado' : 'Empleado desactivado',
            text: 'El estado del empleado ha sido actualizado.',
            variant: 'success',
            position: 'top-right',
        );
    }

    public function getUserInitials($name)
    {
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    public function getEmployeesProperty()
    {
        $query = Employee::with(['category', 'user']);
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('code', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")
                ->orWhere('cedula', 'like', "%{$this->search}%")
                ->orWhere('position', 'like', "%{$this->search}%");
        });

        $query->when($this->departmentFilter, function ($query) {
            $query->where('department', $this->departmentFilter);
        });

        if ($this->statusFilter !== '') {
            $query->where('active', $this->statusFilter);
        }
        
        return $query->paginate($this->perPage);
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('code')->get(),
            'departments' => Employee::getDepartmentOptions(),
            'statusOptions' => Employee::getStatusOptions(),
        ];
    }
}; ?>

<div>
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">{{ __('app.Employees') }}</flux:heading>
            <flux:subheading>{{ __('app.Employee management and user synchronization') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" wire:click="openModal" variant="primary" size="sm">{{ __('app.Add Employee') }}</flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-1"/>

    <!-- Filtros -->
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">{{ $this->employees->total() }} {{ __('app.employee(s)') }}</flux:text>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:input 
                wire:model.live="search" 
                icon="magnifying-glass" 
                placeholder="{{ __('app.Search employees...') }}" 
                label="{{ __('app.Search') }}"
            />
            <flux:select wire:model.live="departmentFilter" placeholder="{{ __('app.Department') }}" label="{{ __('app.Department') }}">
                <flux:select.option value="">{{ __('app.All departments') }}</flux:select.option>
                @foreach($departments as $key => $department)
                    <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="statusFilter" placeholder="{{ __('app.Status') }}" label="{{ __('app.Status') }}">
                <flux:select.option value="">{{ __('app.All statuses') }}</flux:select.option>
                @foreach($statusOptions as $key => $status)
                    <flux:select.option value="{{ $key }}">{{ $status }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <flux:table :paginate="$this->employees">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">{{ __('app.Code') }}</flux:table.column>
            <flux:table.column>{{ __('app.Employee') }}</flux:table.column>
            <flux:table.column>{{ __('app.Contact') }}</flux:table.column>
            <flux:table.column>{{ __('app.Position') }}</flux:table.column>
            <flux:table.column>{{ __('app.Category') }}</flux:table.column>
            <flux:table.column>{{ __('app.Status') }}</flux:table.column>
            <flux:table.column>{{ __('app.User') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->employees as $employee)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:badge variant="pill" color="blue" size="sm">{{ $employee->code }}</flux:badge>
                    </flux:table.cell>
                    
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <flux:text size="sm" class="font-bold text-blue-600">
                                    {{ $this->getUserInitials($employee->name) }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ $employee->name }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $employee->cedula }}</flux:text>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div>
                            @if($employee->user)
                                <flux:text size="sm">{{ $employee->user->email }}</flux:text>
                            @else
                                <flux:text size="sm" class="text-gray-400">Sin usuario</flux:text>
                            @endif
                            <flux:text size="sm" class="text-gray-500 block">{{ $employee->department }}</flux:text>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge color="gray" size="sm">{{ $employee->position }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($employee->category)
                            <div class="flex items-center gap-2">
                                <flux:icon.tag class="w-4 h-4 text-purple-600" />
                                <span class="font-medium">{{ $employee->category->code }}</span>
                            </div>
                        @else
                            <flux:text class="text-gray-400">Sin categoría</flux:text>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$employee->active ? 'green' : 'red'">
                                {{ $employee->active ? 'Activo' : 'Inactivo' }}
                            </flux:badge>
                            <flux:switch wire:click="toggleStatus({{ $employee->id }})" :checked="$employee->active"/>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($employee->user)
                            <flux:badge color="green" size="sm">Sincronizado</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Sin usuario</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button size="sm" icon="pencil" wire:click="edit({{ $employee->id }})" />
                            <flux:button size="sm" icon="trash" variant="danger" wire:click="openDeleteModal({{ $employee->id }})" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modal -->
    <flux:modal name="employee-modal" :open="$showModal" wire:model="showModal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingEmployee ? 'Editar Empleado' : 'Nuevo Empleado' }}</flux:heading>
                <flux:subheading>{{ $editingEmployee ? 'Modifica los datos del empleado' : 'Los cambios se sincronizarán automáticamente con el usuario' }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input 
                            wire:model="code" 
                            label="Código" 
                            placeholder="Ejemplo: EMP001"
                            icon="hashtag"
                        />
                        @error('code') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                    
                    <div>
                        <flux:input 
                            wire:model="name" 
                            label="Nombre Completo" 
                            placeholder="Juan Pérez"
                            icon="user"
                        />
                        @error('name') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input 
                            wire:model="cedula" 
                            label="Cédula" 
                            placeholder="000-0000000-0"
                            icon="identification"
                        />
                        @error('cedula') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                    
                    <div>
                        <flux:input 
                            wire:model="position" 
                            label="Cargo" 
                            placeholder="Supervisor"
                            icon="briefcase"
                        />
                        @error('position') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:select wire:model="department" placeholder="Seleccionar departamento" label="Departamento">
                            @foreach($departments as $key => $department)
                                <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('department') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                    
                    <div>
                        <flux:select wire:model="category_id" placeholder="Seleccionar categoría" label="Categoría">
                            <flux:select.option value="">Sin categoría</flux:select.option>
                            @foreach($categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->code }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('category_id') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>

                <div>
                    <flux:checkbox wire:model="active" label="Empleado activo" />
                </div>
            </div>

            <flux:separator />

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">
                    Cancelar
                </flux:button>
                <flux:button wire:click="save" variant="primary">
                    {{ $editingEmployee ? 'Actualizar' : 'Crear' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-employee-modal" :open="$showDeleteModal" wire:model="showDeleteModal">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <flux:icon.trash class="h-5 w-5 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Eliminar Empleado</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres eliminar este empleado?</flux:subheading>
                </div>
            </div>

            @if($employeeToDelete)
                <div class="bg-gray-50 rounded-lg p-3">
                    <flux:text class="font-medium">{{ $employeeToDelete->name }}</flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Código: {{ $employeeToDelete->code }}
                    </flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Cédula: {{ $employeeToDelete->cedula }}
                    </flux:text>
                    <flux:text size="sm" class="text-gray-600">
                        Cargo: {{ $employeeToDelete->position }}
                    </flux:text>
                </div>
            @endif

            <flux:text class="text-gray-600">
                Esta acción eliminará el empleado permanentemente y no se puede deshacer. También se eliminará el usuario asociado.
            </flux:text>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeDeleteModal">
                    Cancelar
                </flux:button>
                <flux:button variant="danger" wire:click="delete({{ $employeeToDelete->id ?? 0 }})">
                    Eliminar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>