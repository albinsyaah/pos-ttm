<?php

namespace App\Console\Commands;

use App\Services\StockBackfillService;
use Illuminate\Console\Command;

class BackfillStockLedger extends Command
{
    protected $signature = 'stock:backfill
        {--normalize-statuses : Rewrite old status spellings ("Completed", "Pending", ...) to the ones the pages use}
        {--link : Tie existing ledger rows to the transactions they belong to (no stock change)}
        {--create-missing : Write ledger rows for transactions that never moved stock (CHANGES stock balances)}
        {--apply : Actually write the changes; without it this is a dry run}
        {--force : Do not ask for confirmation before writing}';

    protected $description = 'Bring transactions saved before automatic stock handling in line with the stock ledger';

    public function handle(StockBackfillService $service): int
    {
        $steps = array_keys(array_filter([
            StockBackfillService::STEP_NORMALIZE => $this->option('normalize-statuses'),
            StockBackfillService::STEP_LINK => $this->option('link'),
            StockBackfillService::STEP_CREATE => $this->option('create-missing'),
        ]));
        $apply = (bool) $this->option('apply');

        if ($apply && $steps === []) {
            $this->error('--apply needs at least one step: --normalize-statuses, --link and/or --create-missing.');

            return self::FAILURE;
        }

        if ($steps === []) {
            // No step chosen: a read-only look at everything.
            $steps = [
                StockBackfillService::STEP_NORMALIZE,
                StockBackfillService::STEP_LINK,
                StockBackfillService::STEP_CREATE,
            ];
            $this->info('Dry run of all steps. Nothing will be written.');
        } elseif (! $apply) {
            $this->info('Dry run. Nothing will be written; add --apply to write.');
        }

        if ($apply && ! $this->option('force')
            && ! $this->confirm('This writes to the database ('.implode(', ', $steps).'). Have you made a backup?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $report = $service->run($steps, $apply);

        if (isset($report['statuses'])) {
            $s = $report['statuses'];
            $verb = $apply ? 'Rewrote' : 'Would rewrite';
            $this->line("Statuses: {$verb} {$s['purchases']} purchase(s) and {$s['mutations']} internal mutation(s).");
            foreach ($s['unknown'] as $line) {
                $this->warn("  unknown status, left alone: {$line}");
            }
        }

        if (isset($report['ledger'])) {
            $rows = [];
            foreach ($report['ledger']['counts'] as $kind => $classes) {
                foreach ($classes as $class => $count) {
                    $rows[] = [$kind, $class, $count];
                }
            }

            $this->newLine();
            $rows === []
                ? $this->line('Ledger: nothing to do.')
                : $this->table(['Document', 'Result', 'Count'], $rows);

            foreach (array_slice($report['ledger']['problems'], 0, 50) as $line) {
                $this->warn($line);
            }
            $more = count($report['ledger']['problems']) - 50;
            if ($more > 0) {
                $this->warn("... and {$more} more.");
            }
        }

        return self::SUCCESS;
    }
}
