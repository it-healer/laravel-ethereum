<?php

namespace ItHealer\LaravelEthereum\Observers;

use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Jobs\UpdateAlchemyAddressJob;
use ItHealer\LaravelEthereum\Models\EthereumAddress;

/**
 * Keeps the Alchemy webhook in sync with addresses as they are created/deleted.
 * Only acts when a webhook has been provisioned (via ethereum:alchemy-setup).
 */
class EthereumAddressObserver
{
    public function created(EthereumAddress $address): void
    {
        if (Ethereum::findAlchemyWebhook()) {
            UpdateAlchemyAddressJob::dispatch($address, UpdateAlchemyAddressJob::ADD);
        }
    }

    public function deleted(EthereumAddress $address): void
    {
        if (Ethereum::findAlchemyWebhook()) {
            UpdateAlchemyAddressJob::dispatch($address, UpdateAlchemyAddressJob::REMOVE);
        }
    }
}
