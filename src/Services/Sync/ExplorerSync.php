<?php

namespace ItHealer\LaravelEthereum\Services\Sync;

use Illuminate\Support\Facades\Date;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Services\Alchemy\ComputeUnits;
use ItHealer\LaravelEthereum\Services\BaseSync;

class ExplorerSync extends BaseSync
{
    protected EthereumExplorer $explorer;

    public function __construct(EthereumExplorer $explorer)
    {
        $this->explorer = $explorer;
    }

    public function run(): void
    {
        parent::run();

        $this
            ->resetRequests()
            ->syncBlock();
    }

    protected function resetRequests(): self
    {
        if( is_null($this->explorer->requests_at) || !$this->explorer->requests_at->isToday() ) {
            $this->explorer->update([
                'requests' => 0,
                'requests_at' => Date::now(),
            ]);

            $this->log('Requests counter successfully reset.');
        }

        return $this;
    }

    protected function syncBlock(): self
    {
        $api = $this->explorer->api();

        if (!$api->healthCheck()) {
            $this->explorer->update([
                'worked' => false,
            ]);

            throw new \RuntimeException('Explorer '.$this->explorer->name.' health check failed.');
        }

        $this->explorer->increment('requests');
        $this->explorer->recordCredits(
            $api->creditsPerRequest() > 0 ? ComputeUnits::cost('eth_blockNumber') : 0
        );
        $this->explorer->update([
            'sync_at' => Date::now(),
            'worked' => true,
        ]);

        return $this;
    }
}