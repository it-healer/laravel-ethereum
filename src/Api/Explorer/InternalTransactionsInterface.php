<?php

namespace ItHealer\LaravelEthereum\Api\Explorer;

use Closure;
use ItHealer\LaravelEthereum\Api\DTOPaginator;

/**
 * An explorer that knows internal ETH transfers: value a contract sent to an address while
 * executing a transaction (a bridge, an exchange withdrawal, a smart wallet, the ETH of a swap).
 * They are not transactions of the address, so txlist does not return them.
 */
interface InternalTransactionsInterface
{
    public function supportsInternalTransactions(): bool;

    /**
     * getTransactionsPaginator() already returns internal transfers (TransactionDTO with
     * isInternal()), so the sync needs no separate request for them.
     */
    public function combinesInternalTransactions(): bool;

    /**
     * Internal transfers to or from the address, one item per transfer (several may share
     * the hash of their transaction).
     *
     * @return DTOPaginator<\ItHealer\LaravelEthereum\Api\Explorer\DTO\TransactionDTO>
     */
    public function getInternalTransactionsPaginator(string $address, int $startBlock = 0, int $perPage = 10, ?Closure $callback = null): DTOPaginator;
}
