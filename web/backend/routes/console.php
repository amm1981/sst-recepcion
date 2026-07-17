<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('workers:sync-employee-flow')->hourly();
Schedule::command('documents:send-rejected-report')->dailyAt('16:30')->timezone('America/Lima');
