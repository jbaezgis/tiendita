<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class OrdersExport implements FromView
{
    protected $search;
    protected $statusFilter;
    protected $priorityFilter;
    protected $employeeFilter;
    protected $sortBy;
    protected $sortDirection;

    public function __construct($search = '', $statusFilter = '', $priorityFilter = '', $employeeFilter = '', $sortBy = 'created_at', $sortDirection = 'desc')
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->priorityFilter = $priorityFilter;
        $this->employeeFilter = $employeeFilter;
        $this->sortBy = $sortBy;
        $this->sortDirection = $sortDirection;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function view(): View
    {
        $query = Order::with(['employee', 'category', 'items.product', 'approver']);
        
        if ($this->sortBy) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }
        
        $query->when($this->search, function ($query) {
            $query->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('employee', function ($q) {
                    $q->where('name', 'like', "%{$this->search}%");
                });
        });

        $query->when($this->statusFilter, function ($query) {
            $query->where('status', $this->statusFilter);
        });

        $query->when($this->priorityFilter, function ($query) {
            $query->where('priority', $this->priorityFilter);
        });

        $query->when($this->employeeFilter, function ($query) {
            $query->where('employee_id', $this->employeeFilter);
        });

        return view('exports.orders', [
            'orders' => $query->get(),
        ]);
    }
} 