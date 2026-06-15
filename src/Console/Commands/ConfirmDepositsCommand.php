<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumDeposit;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;

/**
 * Address Activity webhooks fire once (when a tx is mined) and are not re-sent as confirmations
 * grow. This re-syncs only the addresses that still have a deposit below the confirmations target.
 */
class ConfirmDepositsCommand extends Command
{
    protected $signature = 'ethereum:confirm-deposits';

    protected $description = 'Re-sync addresses that still have deposits below the confirmations target';

    public function handle(): int
    {
        $target = (int) config('ethereum.confirmations_target', 12);

        /** @var class-string<EthereumDeposit> $depositModel */
        $depositModel = Ethereum::getModel(EthereumModel::Deposit);

        $addressIds = $depositModel::query()
            ->where('confirmations', '<', $target)
            ->distinct()
            ->pluck('address_id');

        if ($addressIds->isEmpty()) {
            $this->line('-- Nothing to confirm.');

            return self::SUCCESS;
        }

        /** @var class-string<EthereumAddress> $addressModel */
        $addressModel = Ethereum::getModel(EthereumModel::Address);

        foreach ($addressModel::query()->whereIn('id', $addressIds)->get() as $address) {
            try {
                App::make(AddressSync::class, ['address' => $address, 'force' => true])->run();
            } catch (\Throwable $e) {
                $this->error('   Address #'.$address->id.': '.$e->getMessage());
            }
        }

        $this->line('-- Completed!');

        return self::SUCCESS;
    }
}
