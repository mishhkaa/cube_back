<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GoogleSheetDataAppendJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('conversion_sender');
    }

    public function handle(): void
    {
        //
    }
}
