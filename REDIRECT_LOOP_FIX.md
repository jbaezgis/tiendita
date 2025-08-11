# Fix para Error de Redirección Infinita

## Problema Identificado

Cuando se agregaba un nuevo usuario que no tenía un empleado vinculado, el sistema presentaba un error de redirección infinita:

```
This page isn't working
tienda.grupoajfa.com redirected you too many times.
Try deleting your cookies.
ERR_TOO_MANY_REDIRECTS
```

## Causa del Problema

El problema se originaba en la siguiente secuencia:

1. **Usuario se autentica** → Sistema redirige a `public.orders` (si tiene roles de empleado)
2. **Middleware `EnsureEmployeeRole`** → Verifica si tiene empleado vinculado
3. **Sin empleado vinculado** → Redirige a `login` (creando el loop infinito)
4. **Usuario ya autenticado** → Sistema redirige de nuevo a `public.orders`
5. **Loop infinito** → Continúa indefinidamente

## Solución Implementada

### 1. Modificación del Middleware `EnsureEmployeeRole`

**Archivo:** `app/Http/Middleware/EnsureEmployeeRole.php`

**Cambio:** Redirigir al dashboard en lugar de login cuando no hay empleado vinculado.

```php
// Antes
if (!$user->employee) {
    return redirect()->route('login')->with('error', 'Tu cuenta no está asociada a un empleado');
}

// Después
if (!$user->employee) {
    return redirect()->route('dashboard')->with('error', 'Tu cuenta no está asociada a un empleado. Contacta al administrador.');
}
```

### 2. Comportamiento de Redirección por Roles

**Archivo:** `app/Http/Middleware/RedirectBasedOnRole.php`

**Lógica implementada:**
- **Usuarios con rol de empleado + empleado vinculado**: Redirigidos a `public/orders` al intentar acceder al dashboard
- **Usuarios con rol de admin**: Acceso completo al dashboard sin redirecciones
- **Usuarios con roles múltiples**: Acceso completo a ambas áreas
- **Usuarios sin empleado vinculado**: Acceso al dashboard con advertencia

### 3. Mejora en la Lógica de Redirección de Login

**Archivo:** `resources/views/livewire/auth/login.blade.php`

**Cambio:** Verificar si el usuario tiene empleado vinculado antes de redirigir a `public.orders`.

```php
// Antes
} elseif ($user->hasRole('empleado') || $user->hasRole('supervisor')) {
    $redirectUrl = route('public.orders');
}

// Después
} elseif (($user->hasRole('empleado') || $user->hasRole('supervisor')) && $user->employee) {
    $redirectUrl = route('public.orders');
} else {
    $redirectUrl = route('dashboard');
}
```

### 4. Optimización del Middleware `RedirectBasedOnRole`

**Archivo:** `app/Http/Middleware/RedirectBasedOnRole.php`

**Cambio:** Solo redirigir a `public.orders` si el usuario tiene empleado vinculado.

```php
// Antes
if ($hasEmployeeRole && !$hasAdminRole && $currentRoute === 'dashboard') {
    return redirect()->route('public.orders');
}

// Después
if ($hasEmployeeRole && !$hasAdminRole && $currentRoute === 'dashboard') {
    if ($user->employee) {
        return redirect()->route('public.orders');
    }
}
```

### 5. Advertencia Visual en el Dashboard

**Archivo:** `resources/views/livewire/dashboard.blade.php`

**Agregado:** Mensaje de advertencia para usuarios sin empleado vinculado.

```php
@if(auth()->user()->hasRole(['empleado', 'supervisor']) && !auth()->user()->employee)
    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>Cuenta no vinculada a empleado</flux:callout.heading>
        <flux:callout.text>
            Tu cuenta tiene roles de empleado pero no está vinculada a un registro de empleado. 
            Esto puede limitar algunas funcionalidades. Contacta al administrador para resolver esto.
        </flux:callout.text>
    </flux:callout>
@endif
```

## Comandos de Administración

### Listar Usuarios sin Empleado Vinculado

```bash
php artisan user:list-without-employee
```

### Vincular Usuario a Empleado

```bash
php artisan user:link-employee {user_id} {employee_id}
```

### Probar Comportamiento de Acceso

```bash
php artisan user:test-access
```

## Comportamiento Resultante

### Usuarios con Empleado Vinculado
- ✅ Redirección automática a `public.orders` al intentar acceder al dashboard
- ✅ Acceso directo a `public.orders` desde el login
- ✅ Funcionalidad completa del sistema

### Usuarios sin Empleado Vinculado
- ✅ Acceso al dashboard (sin loop infinito)
- ✅ Mensaje de advertencia visible
- ✅ Posibilidad de vincular empleado posteriormente

### Usuarios Administradores (Super Admin, Admin)
- ✅ Acceso completo al dashboard
- ✅ Sin redirecciones automáticas
- ✅ Sin restricciones por falta de empleado

### Usuarios con Roles Múltiples (empleado + admin)
- ✅ Acceso completo a ambas áreas
- ✅ Sin redirecciones automáticas

## Archivos Modificados

1. `app/Http/Middleware/EnsureEmployeeRole.php` - Redirección corregida
2. `resources/views/livewire/auth/login.blade.php` - Lógica de login mejorada
3. `app/Http/Middleware/RedirectBasedOnRole.php` - Redirección condicional
4. `resources/views/livewire/dashboard.blade.php` - Advertencia visual
5. `app/Console/Commands/LinkUserToEmployee.php` - Comando de vinculación (nuevo)
6. `app/Console/Commands/ListUsersWithoutEmployee.php` - Comando de listado (nuevo)
7. `app/Console/Commands/TestUserAccess.php` - Comando de prueba (nuevo)

## Beneficios

1. **Eliminación del loop infinito** - Los usuarios pueden acceder al sistema sin problemas
2. **Experiencia de usuario mejorada** - Mensajes claros sobre el estado de la cuenta
3. **Herramientas administrativas** - Comandos para gestionar vinculaciones
4. **Flexibilidad** - Los usuarios pueden tener roles sin empleado vinculado
5. **Seguridad mantenida** - Las restricciones apropiadas siguen aplicándose

## Próximos Pasos Recomendados

1. **Revisar usuarios existentes** - Usar el comando de listado para identificar cuentas problemáticas
2. **Vincular empleados** - Usar el comando de vinculación para resolver casos específicos
3. **Proceso de creación** - Considerar automatizar la vinculación en el proceso de creación de usuarios
4. **Documentación** - Actualizar manuales de usuario sobre el proceso de vinculación 