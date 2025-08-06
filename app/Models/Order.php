<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'employee_id',
        'category_id',
        'order_date',
        'subtotal',
        'total',
        'status',
        'priority',
        'notes',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            $order->order_number = self::generateOrderNumber();
            $order->created_by = auth()->id();
        });

        static::updating(function ($order) {
            $order->updated_by = auth()->id();
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $year = now()->format('Y');
        $lastOrder = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -4) + 1 : 1;
        
        return $prefix . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeDelivered(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $rejector, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
        ]);
    }

    public function deliver(): void
    {
        $this->update(['status' => 'delivered']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function getTotalQuantity(): int
    {
        return $this->items->sum('quantity');
    }

    public function isWithinPurchaseLimit(): bool
    {
        if (!$this->category) {
            return true;
        }

        return $this->total <= $this->category->purchase_limit;
    }

    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'delivered' => 'blue',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    public function getStatusText(): string
    {
        return self::getStatusOptions()[$this->status] ?? 'Desconocido';
    }

    public function getPriorityText(): string
    {
        return self::getPriorityOptions()[$this->priority] ?? 'Desconocido';
    }
}
