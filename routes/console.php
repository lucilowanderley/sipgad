<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Coleta automática dos relógios de ponto (requer o agendador: php artisan schedule:work)
Illuminate\Support\Facades\Schedule::command('relogios:coletar')->everyFiveMinutes()->withoutOverlapping();
