<?php

namespace ItHealer\LaravelEthereum\Concerns;

use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumNode;

trait Node
{
    public function createNode(string $name, string $baseURL, ?string $title = null, ?string $proxy = null): EthereumNode
    {
        /** @var class-string<EthereumNode> $nodeModel */
        $nodeModel = Ethereum::getModel(EthereumModel::Node);
        $node = new $nodeModel([
            'name' => $name,
            'title' => $title,
            'base_url' => $baseURL,
            'proxy' => $proxy,
            'requests' => 1,
            'worked' => true,
        ]);

        $node->api()->getLatestBlockNumber();
        $node->save();

        return $node;
    }

    public function createInfuraNode(string $apiKey, string $name, ?string $title = null, ?string $proxy = null): EthereumNode
    {
        /** @var class-string<EthereumNode> $nodeModel */
        $nodeModel = Ethereum::getModel(EthereumModel::Node);

        $node = new $nodeModel([
            'name' => $name,
            'title' => $title,
            'base_url' => 'https://mainnet.infura.io/v3/'.$apiKey,
            'proxy' => $proxy,
            'requests' => 1,
            'worked' => true,
        ]);

        $node->api()->getLatestBlockNumber();
        $node->save();

        return $node;
    }

    public function getNode(): EthereumNode
    {
        return $this->getModel(EthereumModel::Node)::query()
            ->where('worked', '=', true)
            ->orderBy('requests')
            ->firstOrFail();
    }
}
