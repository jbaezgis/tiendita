<?php

use App\Models\Employee;
use App\Models\Category;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Flux\Flux;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeesExport;
use App\Services\EmployeeImportService;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public $search = '';
    public $departmentFilter = '';
    /** El listado y su exportación son del padrón vigente; los inactivos
     *  tienen su propia pantalla. */
    public $statusFilter = '1';
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

    // --- Importación de integrantes desde Excel ---
    public $importFile;
    public $importing = false;
    public $importFinished = false;
    public $importToken = null;
    public $importTotal = 0;
    public $importProcessed = 0;
    public $importCreated = 0;
    public $importSkipped = 0;
    public $importInvalid = 0;
    public $importUsers = 0;
    public $importError = null;
    public $importChunkSize = 20;
    public $importMissingCategories = [];

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
                heading: 'Integrante actualizado',
                text: 'El integrante ha sido actualizado exitosamente.',
                variant: 'success',
                position: 'top-right',
            );
        } else {
            $employee = Employee::create($validated);
            
            // Forzar sincronización manual después de crear el integrante
            try {
                $employee->syncUser();
                Flux::toast(
                    heading: 'Integrante creado',
                    text: 'El integrante ha sido creado exitosamente con usuario automático.',
                    variant: 'success',
                    position: 'top-right',
                );
            } catch (\Exception $e) {
                Flux::toast(
                    heading: 'Integrante creado',
                    text: 'El integrante ha sido creado exitosamente, pero hubo un problema al crear el usuario.',
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
            heading: 'Integrante eliminado',
            text: 'El integrante ha sido eliminado exitosamente.',
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
            heading: $employee->active ? 'Integrante activado' : 'Integrante desactivado',
            text: 'El estado del integrante ha sido actualizado.',
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

        // Los inactivos viven en su propia pantalla (employees.inactivos),
        // para que este listado sea el padrón vigente.
        $query->where('active', true);

        return $query->paginate($this->perPage);
    }

    public function export()
    {
        return Excel::download(
            new EmployeesExport(
                $this->search,
                $this->departmentFilter,
                $this->statusFilter,
                $this->sortBy,
                $this->sortDirection
            ),
            'integrantes-' . now()->format('d-m-Y h:i a') . '.xlsx'
        );
    }

    public function openImport(): void
    {
        $this->resetImport();
        Flux::modal('employees-import')->show();
    }

    public function resetImport(): void
    {
        $this->reset([
            'importFile', 'importing', 'importFinished', 'importToken', 'importTotal',
            'importProcessed', 'importCreated', 'importSkipped', 'importInvalid',
            'importUsers', 'importError',
        ]);
        $this->importMissingCategories = [];
        $this->resetValidation();
    }

    public function startImport(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls|max:20480',
        ], [], ['importFile' => 'archivo']);

        $this->importError = null;

        try {
            $rows = (new EmployeeImportService())->parse($this->importFile->getRealPath());
        } catch (\Throwable $e) {
            $this->importError = 'No se pudo leer el archivo: ' . $e->getMessage();

            return;
        }

        if (empty($rows)) {
            $this->importError = 'El archivo no contiene filas para importar.';

            return;
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put("imports/{$token}.json", json_encode($rows));

        $this->importToken = $token;
        $this->importTotal = count($rows);
        $this->importProcessed = 0;
        $this->importCreated = 0;
        $this->importSkipped = 0;
        $this->importInvalid = 0;
        $this->importUsers = 0;
        $this->importMissingCategories = [];
        $this->importFinished = false;
        $this->importing = true;

        // Inicia el bucle de procesamiento por lotes (ver x-on:import-continue en la vista).
        $this->dispatch('import-continue');
    }

    public function processImportChunk(): void
    {
        if (! $this->importing || ! $this->importToken) {
            return;
        }

        $path = "imports/{$this->importToken}.json";

        if (! Storage::disk('local')->exists($path)) {
            $this->importError = 'Se perdió el archivo temporal de importación. Vuelve a intentarlo.';
            $this->importing = false;

            return;
        }

        $rows = json_decode(Storage::disk('local')->get($path), true) ?: [];
        $slice = array_slice($rows, $this->importProcessed, $this->importChunkSize);

        $service = new EmployeeImportService();

        foreach ($slice as $row) {
            try {
                $result = $service->importRow($row);

                match ($result['status']) {
                    'created' => $this->importCreated++,
                    'skipped' => $this->importSkipped++,
                    default => $this->importInvalid++,
                };

                if ($result['user_created']) {
                    $this->importUsers++;
                }

                foreach ($result['missing_categories'] as $code) {
                    if (! in_array($code, $this->importMissingCategories, true)) {
                        $this->importMissingCategories[] = $code;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $this->importInvalid++;
            }

            $this->importProcessed++;
        }

        if ($this->importProcessed >= $this->importTotal) {
            $this->importing = false;
            $this->importFinished = true;
            Storage::disk('local')->delete($path);
            $this->resetPage();
        } else {
            // Continúa con el siguiente lote.
            $this->dispatch('import-continue');
        }
    }

    public function getImportProgressProperty(): int
    {
        if ($this->importTotal <= 0) {
            return 0;
        }

        return (int) floor(($this->importProcessed / $this->importTotal) * 100);
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

<div x-on:import-continue.window="$wire.processImportChunk()">
    <div class="md:flex md:justify-between items-center">
        <div class="">
            <flux:heading size="xl">{{ __('Employees') }}</flux:heading>
            <flux:subheading>{{ __('Employee management and user synchronization') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button icon="plus" wire:click="openModal" variant="primary" size="sm">{{ __('Add Employee') }}</flux:button>
            <flux:separator vertical />
            <flux:button wire:click="openImport" icon="arrow-up-tray" variant="outline" size="sm">{{ __('Import Excel') }}</flux:button>
            <flux:button wire:click="export" icon="document-arrow-down" variant="outline" size="sm">{{ __('Export Excel') }}</flux:button>
        </div>
    </div>

    <flux:separator class="mt-4 mb-1"/>

    <!-- Filtros -->
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">{{ $this->employees->total() }} {{ __('employee(s)') }}</flux:text>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:input 
                wire:model.live="search" 
                icon="magnifying-glass" 
                placeholder="{{ __('Search employees...') }}" 
                label="{{ __('Search') }}"
            />
            <flux:select wire:model.live="departmentFilter" placeholder="{{ __('Department') }}" label="{{ __('Department') }}">
                <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
                @foreach($departments as $key => $department)
                    <flux:select.option value="{{ $key }}">{{ $department }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <flux:button icon="archive-box" variant="ghost" :href="route('employees.inactivos')" wire:navigate>
                    Ver histórico de inactivos
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:table :paginate="$this->employees">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Contact') }}</flux:table.column>
            <flux:table.column>{{ __('Position') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('User') }}</flux:table.column>
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
                <flux:heading size="lg">{{ $editingEmployee ? 'Editar Integrante' : 'Nuevo Integrante' }}</flux:heading>
                <flux:subheading>{{ $editingEmployee ? 'Modifica los datos del integrante' : 'Los cambios se sincronizarán automáticamente con el usuario' }}</flux:subheading>
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
                    <flux:checkbox wire:model="active" label="Integrante activo" />
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
                    <flux:heading size="lg">Eliminar Integrante</flux:heading>
                    <flux:subheading>¿Estás seguro de que quieres eliminar este integrante?</flux:subheading>
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
                Esta acción eliminará el integrante permanentemente y no se puede deshacer. También se eliminará el usuario asociado.
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

    <!-- Modal de importación desde Excel -->
    <flux:modal name="employees-import" class="md:w-[640px]" :dismissible="!$importing">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Importar integrantes</flux:heading>
                <flux:text class="mt-2">
                    Sube el archivo Excel de integrantes. Se usará la columna <strong>CODIGO</strong> como
                    identificador: los integrantes que ya existen se mantienen igual y solo se crean los nuevos.
                    Cada integrante nuevo genera automáticamente su usuario con la cédula.
                </flux:text>
            </div>

            {{-- Paso 1: selección de archivo (antes de iniciar) --}}
            @if (! $importing && ! $importFinished)
                <div class="space-y-4">
                    <flux:callout icon="information-circle" heading="Columnas esperadas">
                        <flux:text size="sm">
                            <strong>CODIGO</strong>, <strong>NOMBRE</strong>, <strong>CEDULA</strong>,
                            <strong>CARGO</strong>, <strong>DEPARTAMENTO</strong> (obligatorias) y
                            <strong>CATEGORIA</strong>, <strong>ESTADO</strong> (opcionales).
                            Es el mismo formato que genera el botón Exportar Excel.
                        </flux:text>
                    </flux:callout>

                    <flux:file-upload wire:model="importFile" label="Archivo de integrantes (.xlsx)">
                        <flux:file-upload.dropzone
                            heading="Arrastra el archivo aquí o haz clic para seleccionar"
                            text="Formato Excel (.xlsx o .xls), hasta 20MB"
                        />
                    </flux:file-upload>

                    @error('importFile')
                        <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror

                    @if ($importFile)
                        <flux:file-item heading="{{ $importFile->getClientOriginalName() }}" size="{{ $importFile->getSize() }}">
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="$set('importFile', null)" />
                            </x-slot>
                        </flux:file-item>
                    @endif

                    @if ($importError)
                        <flux:callout variant="danger" icon="exclamation-triangle" heading="No se pudo importar">
                            {{ $importError }}
                        </flux:callout>
                    @endif

                    <div class="flex justify-end gap-2">
                        <flux:button variant="ghost" x-on:click="Flux.modal('employees-import').close()">Cancelar</flux:button>
                        <flux:button
                            variant="primary"
                            wire:click="startImport"
                            icon="arrow-up-tray"
                            :disabled="! $importFile"
                            wire:loading.attr="disabled"
                            wire:target="importFile, startImport"
                        >
                            <span wire:loading.remove wire:target="importFile, startImport">Iniciar importación</span>
                            <span wire:loading wire:target="importFile">Cargando archivo...</span>
                            <span wire:loading wire:target="startImport">Preparando...</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Paso 2: progreso --}}
            @if ($importing)
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <flux:icon.arrow-path class="size-5 animate-spin text-blue-600" />
                        <flux:text class="font-medium">Importando integrantes... por favor no cierres esta ventana.</flux:text>
                    </div>

                    <div>
                        <div class="mb-1 flex justify-between text-sm text-zinc-500 dark:text-zinc-400">
                            <span>Procesando {{ $importProcessed }} de {{ $importTotal }}</span>
                            <span>{{ $this->importProgress }}%</span>
                        </div>
                        <div class="h-3 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div
                                class="h-full rounded-full bg-blue-600 transition-all duration-300 ease-out"
                                style="width: {{ $this->importProgress }}%"
                            ></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3">
                            <div class="text-xl font-bold text-green-600">{{ $importCreated }}</div>
                            <div class="text-xs text-zinc-500">Creados</div>
                        </div>
                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-3">
                            <div class="text-xl font-bold text-zinc-600 dark:text-zinc-300">{{ $importSkipped }}</div>
                            <div class="text-xs text-zinc-500">Ya existían</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                            <div class="text-xl font-bold text-amber-600">{{ $importInvalid }}</div>
                            <div class="text-xs text-zinc-500">Omitidos</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Paso 3: reporte final --}}
            @if ($importFinished)
                <div class="space-y-4">
                    <flux:callout variant="success" icon="check-circle" heading="Importación completada">
                        Se procesaron {{ $importTotal }} registros del archivo y se generaron
                        {{ $importUsers }} usuarios con acceso por cédula.
                    </flux:callout>

                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3">
                            <div class="text-2xl font-bold text-green-600">{{ $importCreated }}</div>
                            <div class="text-xs text-zinc-500">Integrantes creados</div>
                        </div>
                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-3">
                            <div class="text-2xl font-bold text-zinc-600 dark:text-zinc-300">{{ $importSkipped }}</div>
                            <div class="text-xs text-zinc-500">Ya existían</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                            <div class="text-2xl font-bold text-amber-600">{{ $importInvalid }}</div>
                            <div class="text-xs text-zinc-500">Omitidos (datos incompletos)</div>
                        </div>
                    </div>

                    @if (! empty($importMissingCategories))
                        <flux:callout variant="warning" icon="information-circle" heading="Categorías no encontradas">
                            <div class="space-y-2 text-sm">
                                <p>
                                    Estos códigos de categoría no existen en el catálogo, así que los integrantes
                                    se crearon <strong>sin categoría</strong>. Créalas en Categorías y luego
                                    asígnalas al integrante:
                                </p>
                                <div class="font-semibold">{{ implode(', ', $importMissingCategories) }}</div>
                            </div>
                        </flux:callout>
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="primary" x-on:click="Flux.modal('employees-import').close()" wire:click="resetImport">
                            Finalizar
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>
</div>