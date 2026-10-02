<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune old SSO audit logs daily at 02:00 AM (90 days retention)
Schedule::command('sso:prune-logs --days=90')
    ->daily()
    ->at('02:00');
