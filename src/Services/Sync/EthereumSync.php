<?php

namespace ItHealer\LaravelEthereum\Services\Sync;

use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumWallet;
use ItHealer\LaravelEthereum\Services\BaseSync;

class EthereumSync extends BaseSync
{
    public function run(): void
    {
        parent::run();

        $this
            ->syncNodes()
            ->syncExplorers()
            ->syncWallets();
    }

    protected function syncNodes(): static
    {
        /** @var class-string<EthereumNode> $model */
        $model = Ethereum::getModel(EthereumModel::Node);

        $model::query()
            ->where('available', true)
            ->orderBy('sync_at')
            ->orderBy('name')
            ->each(function (EthereumNode $node) {
                $this->log('--- Staring sync Node ' . $node->name . '...');

                $service = App::make(NodeSync::class, compact('node'));

                $service->setLogger($this->logger);

                $service->run();

                $this->log('--- Finished sync Node ' . $node->name, 'success');
            });

        return $this;
    }

    protected function syncExplorers(): static
    {
        /** @var class-string<EthereumExplorer> $model */
        $model = Ethereum::getModel(EthereumModel::Explorer);

        $model::query()
            ->where('available', true)
            ->orderBy('sync_at')
            ->orderBy('name')
            ->each(function (EthereumExplorer $explorer) {
                $this->log('--- Staring sync Explorer ' . $explorer->name . '...');

                $service = App::make(ExplorerSync::class, compact('explorer'));

                $service->setLogger($this->logger);

                $service->run();

                $this->log('--- Finished sync Explorer ' . $explorer->name, 'success');
            });

        return $this;
    }

    protected function syncWallets(): static
    {
        /** @var class-string<EthereumWallet> $model */
        $model = Ethereum::getModel(EthereumModel::Wallet);

        $model::query()
            ->orderBy('sync_at')
            ->orderBy('name')
            ->each(function (EthereumWallet $wallet) {
                $this->log('--- Staring sync Wallet ' . $wallet->name . '...');

                $service = App::make(WalletSync::class, compact('wallet'));

                $service->setLogger($this->logger);

                $service->run();

                $this->log('--- Finished sync Wallet ' . $wallet->name, 'success');
            });

        return $this;
    }
}