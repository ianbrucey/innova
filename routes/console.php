<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// -----------------------------------------------------------------------
// Split Fulfillment Router
// Polls Shopify every 15 minutes and routes line items to Amazon or Irvine.
// Runs without overlapping — if a previous run is still going, skip.
// -----------------------------------------------------------------------
Schedule::command('innova:route-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// -----------------------------------------------------------------------
// Nightly Fulfillment Sync
// Pushes FedEx tracking numbers to Shopify and marks lines complete.
// Time TBC — defaulting to 5:30 PM Pacific. Confirm with Kim.
// -----------------------------------------------------------------------
$syncTime = env('NIGHTLY_SYNC_TIME', '17:30');

Schedule::command('innova:sync-fulfillments')
    ->dailyAt($syncTime)
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
