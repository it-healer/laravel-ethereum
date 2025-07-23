<?php

namespace ItHealer\LaravelEthereum;

use ItHealer\LaravelEthereum\Console\Commands\AddressSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\EthereumSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\ExplorerSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\NodeSyncCommand;
use ItHealer\LaravelEthereum\Console\Commands\WalletSyncCommand;
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
            ->hasCommands(
                NodeSyncCommand::class,
                ExplorerSyncCommand::class,
                AddressSyncCommand::class,
                WalletSyncCommand::class,
                EthereumSyncCommand::class,
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
}
