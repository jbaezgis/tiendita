<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'cedula',
        'position',
        'department',
        'category_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted()
    {
        // Sincronizar con User al actualizar
        static::updated(function ($employee) {
            try {
                $employee->syncUser();
            } catch (\Exception $e) {
                Log::error('Error syncing user for employee: ' . $e->getMessage());
            }
        });

        // Actualizar User si cambia nombre o cédula
        static::updating(function ($employee) {
            if ($employee->isDirty(['name', 'cedula'])) {
                try {
                    $employee->syncUser();
                } catch (\Exception $e) {
                    Log::error('Error syncing user for employee: ' . $e->getMessage());
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Sincronizar datos con la tabla users
     */
    public function syncUser()
    {
        $user = $this->user;
        
        // Limpiar cédula (remover guiones)
        $cleanCedula = preg_replace('/[^0-9]/', '', $this->cedula);
        
        if ($user) {
            $user->update([
                'name' => $this->name,
                'cedula' => $cleanCedula,
                'category_id' => $this->category_id,
            ]);
        } else {
            // Crear usuario si no existe
            $newUser = $this->user()->create([
                'name' => $this->name,
                'email' => $this->generateEmail(),
                'cedula' => $cleanCedula,
                'password' => Hash::make('12345678'), // Password por defecto
                'email_verified_at' => now(),
                'category_id' => $this->category_id,
            ]);
            
            // Asignar rol de empleado automáticamente
            $newUser->assignRole('empleado');
        }
    }

    /**
     * Generar email basado en nombre y cédula
     */
    private function generateEmail()
    {
        $name = strtolower(str_replace(' ', '.', $this->name));
        $cedula = preg_replace('/[^0-9]/', '', $this->cedula);
        return $name . '.' . $cedula . '@empresa.com';
    }

    /**
     * Get status options
     */
    public static function getStatusOptions(): array
    {
        return [
            1 => 'Activo',
            0 => 'Inactivo',
        ];
    }

    /**
     * Get department options
     */
    public static function getDepartmentOptions(): array
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
}
