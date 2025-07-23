<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumWallet;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;
use ItHealer\LaravelEthereum\Services\Sync\EthereumSync;
use ItHealer\LaravelEthereum\Services\Sync\NodeSync;
use ItHealer\LaravelEthereum\Services\Sync\WalletSync;

class EthereumSyncCommand extends Command
{
    protected $signature = 'ethereum:sync';

    protected $description = 'Start Ethereum sync';

    public function handle(): void
    {
        Cache::lock('ethereum', 300)->get(function() {
            $this->line('---- Starting sync Ethereum...');

            try {
                $service = App::make(EthereumSync::class);

                $service->setLogger(fn(string $message, ?string $type) => $this->{$type ? ($type === 'success' ? 'info' : $type) : 'line'}($message));

                $service->run();
            } catch (\Exception $e) {
                $this->error('---- Error: '.$e->getMessage());
            }

            $this->line('---- Completed!');
        });
    }
}
