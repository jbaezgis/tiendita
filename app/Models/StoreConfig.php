<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_open',
        'current_season',
        'season_start_date',
        'season_end_date',
        'store_opening_date',
        'store_closing_date',
        'max_order_amount',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'season_start_date' => 'date',
        'season_end_date' => 'date',
        'store_opening_date' => 'datetime',
        'store_closing_date' => 'datetime',
        'max_order_amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        // Configuración automática al crear/actualizar
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getCurrentConfig(): self
    {
        return self::firstOrCreate([], [
            'is_open' => false,
            'current_season' => 'Sin temporada',
            'max_order_amount' => 10000.00,
        ]);
    }

    public static function isStoreOpen(): bool
    {
        $config = self::getCurrentConfig();
        
        if (!$config->is_open) {
            return false;
        }

        $now = now();
        
        // Verificar si estamos dentro del período de apertura de la tienda
        if ($config->store_opening_date && $config->store_closing_date) {
            return $now->between($config->store_opening_date, $config->store_closing_date);
        }

        return $config->is_open;
    }

    public function getSeasonStatus(): string
    {
        if (!$this->season_start_date || !$this->season_end_date) {
            return 'Sin temporada configurada';
        }

        $now = now();
        
        if ($now->lt($this->season_start_date)) {
            return 'Temporada próxima';
        }
        
        if ($now->gt($this->season_end_date)) {
            return 'Temporada finalizada';
        }
        
        return 'Temporada activa';
    }

    public function getStoreStatus(): string
    {
        if (!self::isStoreOpen()) {
            return 'Cerrada';
        }

        if (!$this->store_opening_date || !$this->store_closing_date) {
            return 'Abierta (sin límite de tiempo)';
        }

        $now = now();
        $daysLeft = $now->diffInDays($this->store_closing_date, false);
        
        if ($daysLeft < 0) {
            return 'Cerrada';
        }
        
        if ($daysLeft <= 3) {
            return "Abierta (cierra en {$daysLeft} días)";
        }
        
        return 'Abierta';
    }

    public static function getSeasonOptions(): array
    {
        return [
            'Útiles Escolares' => 'Útiles Escolares',
            'Navidad' => 'Navidad',
            'Día del Niño' => 'Día del Niño',
            'Día de la Madre' => 'Día de la Madre',
            'Día del Padre' => 'Día del Padre',
            'Pascua' => 'Pascua',
            'Verano' => 'Verano',
            'Invierno' => 'Invierno',
            'Otros' => 'Otros',
        ];
    }
} 