<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

class OptimizeSimpleClear extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'optimize:simple-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the cached bootstrap files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->components->info('Clearing cached bootstrap files.');

        collect([
            'compiled' => fn () => $this->callSilent('clear-compiled') === 0,
            'config' => fn () => $this->callSilent('config:clear') === 0,
            'events' => fn () => $this->callSilent('event:clear') === 0,
            'route' => fn () => $this->callSilent('route:clear') === 0,
            'views' => fn () => $this->callSilent('view:clear') === 0,
        ])->each(fn ($task, $description) => $this->components->task($description, $task));

        $this->newLine();
    }
}
