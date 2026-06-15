<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class AlchemyReconcileCommand extends Command
{
    protected $signature = 'ethereum:alchemy-reconcile';

    protected $description = 'Sync the Alchemy webhook watched-address list with the addresses the package tracks';

    public function handle(): int
    {
        try {
            $diff = Ethereum::reconcileAlchemyWebhook();
            $this->info('-- Reconciled: +'.count($diff['added']).' / -'.count($diff['removed']).' addresses');
        } catch (\Throwable $e) {
            $this->error('-- Error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
