<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'approved_quantity',
        'delivered_quantity',
        'price',
        'subtotal',
        'status',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approve(int $approvedQuantity): void
    {
        $this->update([
            'approved_quantity' => $approvedQuantity,
            'status' => $approvedQuantity > 0 ? 'approved' : 'cancelled',
        ]);
    }

    public function deliver(int $deliveredQuantity): void
    {
        $this->update([
            'delivered_quantity' => $deliveredQuantity,
            'status' => $deliveredQuantity >= $this->approved_quantity ? 'delivered' : 'partial',
        ]);
    }

    public function getRemainingQuantity(): int
    {
        return ($this->approved_quantity ?? $this->quantity) - $this->delivered_quantity;
    }

    public function isFullyDelivered(): bool
    {
        return $this->delivered_quantity >= ($this->approved_quantity ?? $this->quantity);
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'partial' => 'orange',
            'delivered' => 'blue',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
