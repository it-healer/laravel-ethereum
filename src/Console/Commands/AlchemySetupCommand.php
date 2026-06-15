<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class AlchemySetupCommand extends Command
{
    protected $signature = 'ethereum:alchemy-setup {--reconcile : Push all tracked addresses after creating the webhook}';

    protected $description = 'Create (or reuse) the Alchemy Address Activity webhook for Ethereum';

    public function handle(): int
    {
        try {
            $webhook = Ethereum::ensureAlchemyWebhook();
        } catch (\Throwable $e) {
            $this->error('-- Error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('-- Webhook ready');
        $this->line('   webhook_id: '.$webhook->webhook_id);
        $this->line('   receiver:   '.(config('ethereum.alchemy.webhook.url')
            ?: rtrim((string) config('app.url'), '/').'/'.ltrim((string) config('ethereum.alchemy.webhook.path'), '/')));

        if ($this->option('reconcile')) {
            $diff = Ethereum::reconcileAlchemyWebhook();
            $this->info('-- Reconciled: +'.count($diff['added']).' / -'.count($diff['removed']).' addresses');
        }

        return self::SUCCESS;
    }
}
