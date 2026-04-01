<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $search;
    protected $departmentFilter;
    protected $statusFilter;
    protected $sortBy;
    protected $sortDirection;

    public function __construct(
        $search = '',
        $departmentFilter = '',
        $statusFilter = '',
        $sortBy = 'id',
        $sortDirection = 'desc'
    ) {
        $this->search = $search;
        $this->departmentFilter = $departmentFilter;
        $this->statusFilter = $statusFilter;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
    }

    public function collection()
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

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Codigo',
            'Nombre',
            'Cedula',
            'Cargo',
            'Departamento',
            'Categoria',
            'Estado',
            'Email usuario',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->code,
            $employee->name,
            $employee->cedula,
            $employee->position,
            $employee->department,
            $employee->category?->code ?? 'Sin categoria',
            $employee->active ? 'Activo' : 'Inactivo',
            $employee->user?->email ?? 'Sin usuario',
        ];
    }
}
