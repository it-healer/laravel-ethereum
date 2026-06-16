<?php

namespace ItHealer\LaravelEthereum\Services\Infura;

/**
 * Infura credit costs per JSON-RPC method (Infura/MetaMask credit-based pricing). Infura meters
 * usage in "Daily Credits" that reset every day. Costs can be overridden via the
 * `ethereum.infura_credits` config. Source: MetaMask Developer docs — credit cost table.
 */
class InfuraCredits
{
    /**
     * @var array<string, int>
     */
    public const COSTS = [
        'eth_blockNumber' => 80,
        'eth_call' => 80,
        'eth_getBalance' => 80,
        'eth_getBlockByNumber' => 80,
        'eth_getTransactionCount' => 80,
        'eth_getTransactionReceipt' => 80,
        'eth_gasPrice' => 80,
        'eth_maxPriorityFeePerGas' => 80,
        'eth_feeHistory' => 80,
        'eth_estimateGas' => 300,
        'eth_sendRawTransaction' => 80,
        'eth_getLogs' => 255,
        'eth_chainId' => 5,
        'net_version' => 5,
    ];

    public const DEFAULT_COST = 80;

    public static function cost(string $method): int
    {
        $overrides = (array) config('ethereum.infura_credits', []);

        return (int) ($overrides[$method] ?? self::COSTS[$method] ?? self::DEFAULT_COST);
    }
}
