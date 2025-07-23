<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;
use ItHealer\LaravelEthereum\Services\Sync\NodeSync;

class AddressSyncCommand extends Command
{
    protected $signature = 'ethereum:address-sync {address_id}';

    protected $description = 'Start Ethereum address sync';

    public function handle(): void
    {
        $addressId = (int)$this->argument('address_id');

        $this->line('-- Starting sync Ethereum address #'.$addressId.' ...');

        try {
            /** @var class-string<EthereumAddress> $model */
            $model = Ethereum::getModel(EthereumModel::Address);
            $address = $model::findOrFail($addressId);

            $this->line('-- Address: *'.$address->address.'*'.$address->title);

            $service = App::make(AddressSync::class, [
                'address' => $address,
                'force' => true,
            ]);

            $service->setLogger(fn(string $message, ?string $type) => $this->{$type ? ($type === 'success' ? 'info' : $type) : 'line'}($message));

            $service->run();
        } catch (\Exception $e) {
            $this->error('-- Error: '.$e->getMessage());
        }

        $this->line('-- Completed!');
    }
}
