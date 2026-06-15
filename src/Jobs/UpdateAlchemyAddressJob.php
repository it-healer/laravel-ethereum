<?php

namespace ItHealer\LaravelEthereum\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;

/**
 * Adds or removes a single address in the Alchemy webhook. Dispatched by the address
 * observer when `ethereum.alchemy.auto_subscribe` is enabled.
 */
class UpdateAlchemyAddressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ADD = 'add';
    public const REMOVE = 'remove';

    public function __construct(
        public EthereumAddress $address,
        public string $action,
    ) {
        $this->onConnection(config('ethereum.alchemy.queue.connection'));
        $this->onQueue(config('ethereum.alchemy.queue.name'));
    }

    public function handle(): void
    {
        if ($this->action === self::REMOVE) {
            Ethereum::unsubscribeAlchemyAddress($this->address->address);

            return;
        }

        Ethereum::subscribeAlchemyAddress($this->address->address);
    }
}
