<?php

namespace ItHealer\LaravelEthereum\Api\Explorer;

use Closure;
use ItHealer\LaravelEthereum\Api\DTOPaginator;

interface ExplorerApiInterface
{
    /**
     * @return DTOPaginator<\ItHealer\LaravelEthereum\Api\Explorer\DTO\TransactionDTO>
     */
    public function getTransactionsPaginator(string $address, int $startBlock = 0, int $perPage = 10, ?Closure $callback = null): DTOPaginator;

    /**
     * @return DTOPaginator<\ItHealer\LaravelEthereum\Api\Explorer\DTO\TokenTransactionDTO>
     */
    public function getTokenTransactionsPaginator(string $address, ?string $contract = null, int $startBlock = 0, int $perPage = 10, ?Closure $callback = null): DTOPaginator;

    public function healthCheck(): bool;

    /**
     * Compute Units billed per underlying API request (0 for non-CU providers).
     */
    public function creditsPerRequest(): int;
}
