<?php

namespace ItHealer\LaravelEthereum\Services\Sync;

use Illuminate\Support\Facades\Date;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Services\BaseSync;

class NodeSync extends BaseSync
{
    protected EthereumNode $node;

    public function __construct(EthereumNode $node)
    {
        $this->node = $node;
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
        if( is_null($this->node->requests_at) || !$this->node->requests_at->isToday() ) {
            $this->node->update([
                'requests' => 0,
                'requests_at' => Date::now(),
            ]);

            $this->log('Requests counter successfully reset.');
        }

        return $this;
    }

    protected function syncBlock(): self
    {
        try {
            $api = $this->node->api();
            $api->getLatestBlockNumber();
        }
        catch(\Exception $e) {
            $this->node->update([
                'worked' => false,
            ]);

            throw $e;
        }

        $this->node->increment('requests');
        $this->node->update([
            'sync_at' => Date::now(),
            'worked' => true,
        ]);

        return $this;
    }
}