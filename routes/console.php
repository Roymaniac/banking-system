<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Notification\Infrastructure\Outbox\ProcessEmailOutboxJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The scheduler only queues a lightweight worker; encrypted email contents
// remain in the outbox until that worker confirms successful delivery.
Schedule::job(new ProcessEmailOutboxJob)
    ->everyMinute()
    ->withoutOverlapping();
