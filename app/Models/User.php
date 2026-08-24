<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'cedula',
        'employee_id',
        'category_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the employee that owns the user
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the category that owns the user
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Find the user instance for the given username.
     *
     * @param  string  $username
     * @return User|null
     */
    public function findForPassport($username)
    {
        return $this->where('email', $username)->first();
    }

    /**
     * Scope to find user by email or cédula
     *
     * @param  Builder  $query
     * @param  string  $identifier
     * @return Builder
     */
    public function scopeFindByEmailOrCedula($query, $identifier)
    {
        return $query->where(function ($q) use ($identifier) {
            $q->where('email', $identifier)
                ->orWhere('cedula', $identifier);
        });
    }

    /**
     * Find user by email or cédula
     *
     * @param  string  $identifier
     * @return User|null
     */
    public static function findByEmailOrCedula($identifier)
    {
        return static::where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('cedula', $identifier);
        })->first();
    }

    /**
     * ¿La cuenta pertenece a un integrante que ya no está activo?
     *
     * Los integrantes que salen de la empresa conservan su usuario y su
     * historial de pedidos —por eso no se borran—, pero no deben poder seguir
     * comprando. Las cuentas administrativas no cuelgan de un integrante, así
     * que nunca quedan bloqueadas por esta vía.
     */
    public function isBlockedByInactiveEmployee(): bool
    {
        if (! $this->employee_id) {
            return false;
        }

        // Se consulta directo en vez de usar la relación: si ya venía cargada
        // en memoria (por ejemplo desde antes de la sincronización), el estado
        // en caché diría que sigue activo.
        return ! Employee::whereKey($this->employee_id)->where('active', true)->exists();
    }
}
