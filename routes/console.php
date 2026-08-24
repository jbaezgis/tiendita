<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cultiva es la fuente de verdad del expediente: el padrón de integrantes y
// sus usuarios de la tienda se sincronizan cada hora.
// Requiere que el cron del servidor ejecute `php artisan schedule:run`.
Schedule::command('cultiva:sync-integrantes')
    ->hourly()
    ->withoutOverlapping();
