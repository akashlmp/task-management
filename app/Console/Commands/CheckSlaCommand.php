<?php

namespace App\Console\Commands;

use App\Services\SlaService;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sla:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor active tasks for approaching SLA warning thresholds and breaches, dispatching notifications.';

    /**
     * Execute the console command.
     */
    public function handle(SlaService $slaService): int
    {
        $this->info('Evaluating active tasks for SLA compliance...');

        $result = $slaService->checkSlaBreachesAndWarnings();

        $this->info("SLA check completed: {$result['warnings_sent']} warnings sent, {$result['breaches_sent']} breaches flagged.");

        return Command::SUCCESS;
    }
}
