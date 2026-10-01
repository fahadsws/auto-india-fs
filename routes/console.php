<?php

use Illuminate\Support\Facades\Schedule;

// Optional: with a real server cron (`* * * * * php artisan schedule:run`) this mirrors the web cron route.
Schedule::command('automation:run')->everyFiveMinutes()->withoutOverlapping();
