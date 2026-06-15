<?php

namespace ItHealer\LaravelEthereum\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;

/**
 * Targeted AddressSync for one address, triggered by an inbound Alchemy webhook.
 * Unique per address so a burst of activity does not spawn overlapping scans.
 */
class SyncEthereumAddressJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public EthereumAddress $address,
        public bool $force = true,
    ) {
        $this->onConnection(config('ethereum.alchemy.queue.connection'));
        $this->onQueue(config('ethereum.alchemy.queue.name'));
    }

    public function uniqueId(): string
    {
        return 'ethereum-sync:'.$this->address->getKey();
    }

    public function handle(): void
    {
        App::make(AddressSync::class, [
            'address' => $this->address,
            'force' => $this->force,
        ])->run();
    }
}
