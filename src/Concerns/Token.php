<?php

namespace ItHealer\LaravelEthereum\Concerns;

use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumToken;

trait Token
{
    public function createToken(string $contract, ?EthereumNode $node = null)
    {
        $contract = Str::lower($contract);

        if( !$node ) {
            $node = Ethereum::getNode();
        }

        $node->increment('requests', 3);

        $api = $node->api();
        $name = $api->getTokenName($contract);
        $symbol = $api->getTokenSymbol($contract);
        $decimals = $api->getTokenDecimals($contract);

        /** @var class-string<EthereumToken> $model */
        $model = Ethereum::getModel(EthereumModel::Token);

        return $model::create([
            'address' => $contract,
            'name' => $name,
            'symbol' => $symbol,
            'decimals' => $decimals,
        ]);
    }
}
