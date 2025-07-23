<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumWallet;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;
use ItHealer\LaravelEthereum\Services\Sync\NodeSync;
use ItHealer\LaravelEthereum\Services\Sync\WalletSync;

class WalletSyncCommand extends Command
{
    protected $signature = 'ethereum:wallet-sync {wallet_id}';

    protected $description = 'Start Ethereum wallet sync';

    public function handle(): void
    {
        $walletId = (int)$this->argument('wallet_id');

        $this->line('-- Starting sync Ethereum wallet #'.$walletId.' ...');

        try {
            /** @var class-string<EthereumWallet> $model */
            $model = Ethereum::getModel(EthereumModel::Wallet);
            $wallet = $model::findOrFail($walletId);

            $this->line('-- Wallet: *'.$wallet->name.'*'.$wallet->title);

            $service = App::make(WalletSync::class, compact('wallet'));

            $service->setLogger(fn(string $message, ?string $type) => $this->{$type ? ($type === 'success' ? 'info' : $type) : 'line'}($message));

            $service->run();
        } catch (\Exception $e) {
            $this->error('-- Error: '.$e->getMessage());
        }

        $this->line('-- Completed!');
    }
}
