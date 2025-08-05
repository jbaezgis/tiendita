<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $login_field = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;
    public string $message = '';

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->message = '🔄 Intentando login...';
        \Log::info('Login attempt started', ['login_field' => $this->login_field]);
        
        try {
            $this->validate();
            $this->message = '✅ Validación exitosa';
            \Log::info('Validation passed');

            $this->ensureIsNotRateLimited();
            $this->message = '✅ Rate limit ok';
            \Log::info('Rate limit check passed');

            \Log::info('Attempting auth with credentials', ['login_field' => $this->login_field]);
            
            // Buscar usuario por email o cédula
            $user = \App\Models\User::findByEmailOrCedula($this->login_field);
            
            if (!$user || !Auth::attempt(['id' => $user->id, 'password' => $this->password], $this->remember)) {
                RateLimiter::hit($this->throttleKey());
                $this->message = '❌ Credenciales incorrectas';
                \Log::info('Auth failed');

                throw ValidationException::withMessages([
                    'login_field' => __('auth.failed'),
                ]);
            }

            \Log::info('Auth successful, user: ' . Auth::user()->name);
            RateLimiter::clear($this->throttleKey());
            Session::regenerate();
            
            $this->message = '✅ Login exitoso! Usuario: ' . Auth::user()->name;
            \Log::info('About to redirect based on user role');
            
            // Determinar la ruta de redirección basada en el rol del usuario
            $user = Auth::user();
            $redirectUrl = route('public.orders'); // Ruta por defecto para empleados y supervisores
            
            if ($user->hasRole('Super Admin') || $user->hasRole('admin')) {
                $redirectUrl = route('dashboard');
                \Log::info('User has Super Admin/Admin role, redirecting to dashboard');
            } elseif ($user->hasRole('empleado') || $user->hasRole('supervisor')) {
                $redirectUrl = route('public.orders');
                \Log::info('User has empleado/supervisor role, redirecting to public/orders');
            }
            
            \Log::info('Using JavaScript redirect to: ' . $redirectUrl);
            $this->dispatch('redirect-now', $redirectUrl);
            $this->message = '✅ Redirigiendo a: ' . $redirectUrl;
            \Log::info('JavaScript redirect dispatched with URL: ' . $redirectUrl);
            
        } catch (ValidationException $e) {
            $this->message = '❌ Error de validación: ' . implode(', ', $e->validator->errors()->all());
            \Log::error('Validation error: ' . implode(', ', $e->validator->errors()->all()));
            throw $e;
        } catch (\Exception $e) {
            $this->message = '❌ Error inesperado: ' . $e->getMessage();
            \Log::error('Unexpected error during login: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login_field' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login_field).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('app.Log in to your account')" :description="__('app.Enter your email or cedula and password below to log in')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />
    
    @if($message)
        <div class="text-center p-3 bg-blue-100 border border-blue-200 rounded">
            {{ $message }}
        </div>
    @endif

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- Email or Cedula -->
        <flux:input
            wire:model="login_field"
            :label="__('app.Email or Cedula')"
            type="text"
            required
            autofocus
            autocomplete="username"
            placeholder="001-0000000-0 o nombre.apellido@ajfaweb.com"
        />

        <!-- Password -->
        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('app.Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('app.Password')"
                viewable
            />

            @if (Route::has('password.request'))
                <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('app.Forgot your password?') }}
                </flux:link>
            @endif
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" :label="__('app.Remember me')" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('app.Log in') }}</flux:button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="text-center">
            <flux:link :href="route('register')" wire:navigate>
                {{ __('app.Don\'t have an account?') }}
            </flux:link>
        </div>
    @endif
</div>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('redirect-now', (url) => {
        console.log('Redirecting to:', url);
        window.location.href = url;
    });
});
</script>
