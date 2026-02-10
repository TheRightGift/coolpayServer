<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly reconciliation of pending Paystack payments/payouts
Schedule::command('reconcile:paystack --limit=100')->dailyAt('02:00');
