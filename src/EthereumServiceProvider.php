<?php

namespace ItHealer\LaravelEthereum;

use ItHealer\LaravelEthereum\Console\Commands\AddressSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\AlchemyReconcileCommand;
use ItHealer\LaravelEthereum\Console\Commands\AlchemySetupCommand;
use ItHealer\LaravelEthereum\Console\Commands\ConfirmDepositsCommand;
use ItHealer\LaravelEthereum\Console\Commands\EthereumSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\ExplorerSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\NodeSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\WalletSyncCommand;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Observers\EthereumAddressObserver;
use ItHealer\LaravelEthereum\Services\Alchemy\AlchemyNotifyClient;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EthereumServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ethereum')
            ->hasConfigFile()
            ->hasRoute('webhook')
            ->hasCommands(
                NodeSyncCommand::class,
                ExplorerSyncCommand::class,
                AddressSyncCommand::class,
                WalletSyncCommand::class,
                EthereumSyncCommand::class,
                AlchemySetupCommand::class,
                AlchemyReconcileCommand::class,
                ConfirmDepositsCommand::class,
            )
            ->discoversMigrations()
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('it-healer/laravel-ethereum');
            });

        $this->app->singleton(Ethereum::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AlchemyNotifyClient::class, function () {
            return new AlchemyNotifyClient(
                authToken: (string) config('ethereum.alchemy.auth_token'),
                apiUrl: (string) config('ethereum.alchemy.api_url', 'https://dashboard.alchemy.com/api'),
                proxy: config('ethereum.proxy'),
            );
        });
    }

    public function packageBooted(): void
    {
        if (config('ethereum.alchemy.auto_subscribe', false)) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $addressModel */
            $addressModel = config('ethereum.models.'.EthereumModel::Address->value);

            $addressModel::observe(EthereumAddressObserver::class);
        }
    }
}
