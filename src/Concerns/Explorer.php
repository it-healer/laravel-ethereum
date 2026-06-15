<?php

namespace ItHealer\LaravelEthereum\Concerns;

use ItHealer\LaravelEthereum\Api\Explorer\DTO\GasOracleDTO;
use ItHealer\LaravelEthereum\Api\Explorer\ExplorerApi;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Enums\ExplorerDriver;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Services\AlchemyUrlFactory;

trait Explorer
{
    public function createExplorer(
        string $name,
        string $baseURL,
        string $apiKey,
        ExplorerDriver $driver = ExplorerDriver::EtherscanV2,
        ?string $title = null,
        ?string $proxy = null,
    ): EthereumExplorer {
        /** @var class-string<EthereumExplorer> $explorerModel */
        $explorerModel = Ethereum::getModel(EthereumModel::Explorer);
        $explorer = new $explorerModel([
            'name' => $name,
            'title' => $title,
            'driver' => $driver,
            'base_url' => $baseURL,
            'api_key' => $apiKey,
            'proxy' => $proxy,
            'requests' => 1,
            'worked' => true,
        ]);

        if (!$explorer->api()->healthCheck()) {
            throw new \RuntimeException("Explorer {$name} health check failed.");
        }
        $explorer->save();

        return $explorer;
    }

    public function createEtherscanExplorer(string $apiKey, string $name, ?string $title = null, ?string $proxy = null): EthereumExplorer
    {
        return $this->createExplorer(
            name: $name,
            baseURL: 'https://api.etherscan.io/v2/api',
            apiKey: $apiKey,
            driver: ExplorerDriver::EtherscanV2,
            title: $title,
            proxy: $proxy,
        );
    }

    public function createAlchemyExplorer(string $apiKey, string $name, ?string $title = null, ?string $proxy = null): EthereumExplorer
    {
        $chainId = (int) config('ethereum.explorer.chain_id', 1);
        $baseURL = AlchemyUrlFactory::make($chainId, $apiKey);

        if (!$baseURL) {
            throw new \InvalidArgumentException("Alchemy does not support chain id {$chainId}.");
        }

        return $this->createExplorer(
            name: $name,
            baseURL: $baseURL,
            apiKey: $apiKey,
            driver: ExplorerDriver::Alchemy,
            title: $title,
            proxy: $proxy,
        );
    }

    public function getExplorer(): EthereumExplorer
    {
        return $this->getModel(EthereumModel::Explorer)::query()
            ->where('worked', '=', true)
            ->where('available', '=', true)
            ->orderByCredits()
            ->orderBy('requests')
            ->firstOrFail();
    }

    public function getGasOracle(): GasOracleDTO
    {
        /** @var EthereumExplorer $explorer */
        $explorer = $this->getModel(EthereumModel::Explorer)::query()
            ->where('worked', '=', true)
            ->where('driver', ExplorerDriver::EtherscanV2->value)
            ->orderBy('requests')
            ->firstOrFail();

        $api = $explorer->api();

        if (!$api instanceof ExplorerApi) {
            throw new \RuntimeException('Gas oracle requires an Etherscan explorer.');
        }

        return $api->getGasOracle();
    }
}
