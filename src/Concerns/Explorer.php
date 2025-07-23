<?php

namespace ItHealer\LaravelEthereum\Concerns;

use ItHealer\LaravelEthereum\Api\Explorer\DTO\GasOracleDTO;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;

trait Explorer
{
    public function createExplorer(string $name, string $baseURL, string $apiKey, ?string $title = null, ?string $proxy = null): EthereumExplorer
    {
        /** @var class-string<EthereumExplorer> $explorerModel */
        $explorerModel = Ethereum::getModel(EthereumModel::Explorer);
        $explorer = new $explorerModel([
            'name' => $name,
            'title' => $title,
            'base_url' => $baseURL,
            'api_key' => $apiKey,
            'proxy' => $proxy,
            'requests' => 1,
            'worked' => true,
        ]);

        $explorer->api()->getApiLimit();
        $explorer->save();

        return $explorer;
    }

    public function createEtherscanExplorer(string $apiKey, string $name, ?string $title = null, ?string $proxy = null): EthereumExplorer
    {
        /** @var class-string<EthereumExplorer> $explorerModel */
        $explorerModel = Ethereum::getModel(EthereumModel::Explorer);
        $explorer = new $explorerModel([
            'name' => $name,
            'title' => $title,
            'base_url' => 'https://api.etherscan.io/api',
            'api_key' => $apiKey,
            'proxy' => $proxy,
            'requests' => 1,
            'worked' => true,
        ]);

        $explorer->api()->getApiLimit();
        $explorer->save();

        return $explorer;
    }

    public function getExplorer(): EthereumExplorer
    {
        return $this->getModel(EthereumModel::Explorer)::query()
            ->where('worked', '=', true)
            ->orderBy('requests')
            ->firstOrFail();
    }

    public function getGasOracle(): GasOracleDTO
    {
        return $this->getExplorer()->api()->getGasOracle();
    }
}
