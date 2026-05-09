<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('iNat:check-on-date ' . now()->subWeek()->format('Y-m-d'))
    ->dailyAt('10:00');
