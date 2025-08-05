<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.public')] class extends Component {
    //
}; ?>

<div>
<div class="bg-gradient-to-br from-blue-50 via-white to-indigo-50">
    <!-- Header -->
    <header class="absolute inset-x-0 top-0 z-50">
      <nav aria-label="Global" class="flex items-center justify-between p-6 lg:px-8">
        <div class="flex lg:flex-1">
          <a href="#" class="-m-1.5 p-1.5">
            <span class="sr-only">Grupo AJFA</span>
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-12 w-auto" />
          </a>
        </div>
        <div class="flex lg:hidden">
          <button type="button" command="show-modal" commandfor="mobile-menu" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700">
            <span class="sr-only">Open main menu</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
              <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
       
        <div class="hidden lg:flex lg:flex-1 lg:justify-end">
          <a href="{{ route('login') }}" class="text-sm/6 font-semibold text-gray-900 hover:text-indigo-600 transition-colors">Acceder <span aria-hidden="true">&rarr;</span></a>
        </div>
      </nav>
      <el-dialog>
        <dialog id="mobile-menu" class="backdrop:bg-transparent lg:hidden">
          <div tabindex="0" class="fixed inset-0 focus:outline-none">
            <el-dialog-panel class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white p-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
              <div class="flex items-center justify-between">
                <a href="#" class="-m-1.5 p-1.5">
                  <span class="sr-only">Grupo AJFA</span>
                  <img src="{{ asset('images/logo.png') }}" alt="" class="h-8 w-auto" />
                </a>
                <button type="button" command="close" commandfor="mobile-menu" class="-m-2.5 rounded-md p-2.5 text-gray-700">
                  <span class="sr-only">Close menu</span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" data-slot="icon" aria-hidden="true" class="size-6">
                    <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
              </div>
            </el-dialog-panel>
          </div>
        </dialog>
      </el-dialog>
    </header>
  
    <!-- Hero Section -->
    <div class="relative isolate px-6 pt-14 lg:px-8">
      <!-- Background decoration -->
      <div aria-hidden="true" class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80">
        <div style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" class="relative left-[calc(50%-11rem)] aspect-1155/678 w-144.5 -translate-x-1/2 rotate-30 bg-gradient-to-tr from-blue-400 to-indigo-600 opacity-20 sm:left-[calc(50%-30rem)] sm:w-288.75"></div>
      </div>
      
      <div class="mx-auto max-w-6xl py-16 sm:py-24 lg:py-32">
        <!-- WhatsApp Contact Banner -->
        <div class="hidden sm:mb-8 sm:flex sm:justify-center">
          <div class="relative rounded-full px-6 py-3 text-sm/6 text-gray-700 ring-1 ring-gray-900/10 hover:ring-gray-900/20 bg-white/80 backdrop-blur-sm shadow-sm">
            <span class="flex items-center gap-2">
              <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
              </svg>
              ¿No tienes acceso? Contacta con el administrador
              <a href="https://wa.me/+18294522055" class="font-semibold text-green-600 hover:text-green-700 transition-colors">
                <span aria-hidden="true" class="absolute inset-0"></span>Abrir WhatsApp <span aria-hidden="true">&rarr;</span>
              </a>
            </span>
          </div>
        </div>
        
        <!-- Main Content -->
        <div class="">
          <!-- Left Column - Text Content -->
          <div class="text-center">
            <!-- Title -->
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl lg:text-7xl">
              <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                Tiendita AJFA
              </span>
            </h1>
            <div class="text-xl font-bold bg-gradient-to-r from-sky-600 to-indigo-800 bg-clip-text text-transparent">
              Tienda de productos de Grupo AJFA
            </div>
            
            <!-- Subtitle -->
            <p class="mt-6 text-xl text-gray-600 sm:text-2xl max-w-3xl mx-auto leading-relaxed">
              Bienvenido a la tienda exclusiva de útiles escolares para integrantes de 
              <span class="font-semibold text-gray-900">Grupo AJFA</span>. 
              Encuentra todo lo que necesitas para el éxito académico de tus hijos.
            </p>
            
            <!-- CTA Buttons -->
            <div class="mt-12 flex flex-col sm:flex-row justify-center items-center gap-4">
              <a href="{{ route('public.orders') }}" class="group relative px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                Ir a la Tienda
                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </a>
              
              <a href="https://wa.me/+18294522055" class="group px-8 py-4 bg-green-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                </svg>
                Contactar por WhatsApp
                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </a>
            </div>
          </div>
        </div>
        
        <!-- Features Section -->
        <div class="mt-20">
          <div class="relative">
            {{-- <img src="{{ asset('images/school/features-bg.svg') }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-30" /> --}}
            <div class="relative z-10 grid grid-cols-1 sm:grid-cols-3 gap-8 max-w-5xl mx-auto">
              <div class="flex flex-col items-center p-6 bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200/50">
                <div class="p-4 bg-blue-100 rounded-full mb-4">
                  <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg mb-2">Calidad Garantizada</h3>
                <p class="text-gray-600 text-center">Productos de primera calidad seleccionados especialmente para el éxito académico</p>
              </div>
              
              <div class="flex flex-col items-center p-6 bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200/50">
                <div class="p-4 bg-green-100 rounded-full mb-4">
                  <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                  </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg mb-2">Precios Especiales</h3>
                <p class="text-gray-600 text-center">Descuentos exclusivos para empleados de Grupo AJFA</p>
              </div>
              
              <div class="flex flex-col items-center p-6 bg-white/80 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200/50">
                <div class="p-4 bg-purple-100 rounded-full mb-4">
                  <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                  </svg>
                </div>
                <h3 class="font-bold text-gray-900 text-lg mb-2">Entrega Rápida</h3>
                <p class="text-gray-600 text-center">Pedidos procesados eficientemente para tu comodidad</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Additional Info -->
        <div class="mt-16 p-8 bg-white/60 backdrop-blur-sm rounded-2xl shadow-sm border border-gray-200/50 max-w-4xl mx-auto">
          <p class="text-gray-700 text-center">
            <span class="font-semibold text-lg">¿Eres empleado de Grupo AJFA?</span><br>
            <span class="text-base">Accede con tu credencial para disfrutar de precios especiales y descuentos exclusivos en útiles escolares de alta calidad.</span>
          </p>
        </div>
      </div>
      
      <!-- Bottom decoration -->
      <div aria-hidden="true" class="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]">
        <div style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" class="relative left-[calc(50%+3rem)] aspect-1155/678 w-144.5 -translate-x-1/2 bg-gradient-to-tr from-blue-400 to-indigo-600 opacity-20 sm:left-[calc(50%+36rem)] sm:w-288.75"></div>
      </div>
    </div>
  </div>
  
</div>
