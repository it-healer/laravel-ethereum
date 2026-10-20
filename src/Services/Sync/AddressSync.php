<?php

namespace ItHealer\LaravelEthereum\Services\Sync;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Api\Explorer\DTO\TokenTransactionDTO;
use ItHealer\LaravelEthereum\Api\Explorer\DTO\TransactionDTO;
use ItHealer\LaravelEthereum\Api\Explorer\ExplorerApiInterface;
use ItHealer\LaravelEthereum\Api\Explorer\InternalTransactionsInterface;
use ItHealer\LaravelEthereum\Api\Node\NodeApi;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Enums\TransactionType;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumDeposit;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumToken;
use ItHealer\LaravelEthereum\Models\EthereumTransaction;
use ItHealer\LaravelEthereum\Models\EthereumWallet;
use ItHealer\LaravelEthereum\Services\BaseSync;
use ItHealer\LaravelEthereum\Webhook\WebhookHandlerInterface;

class AddressSync extends BaseSync
{
    protected EthereumAddress $address;
    protected EthereumWallet $wallet;
    protected EthereumNode $node;
    protected NodeApi $nodeApi;
    protected EthereumExplorer $explorer;
    protected ExplorerApiInterface $explorerApi;
    /** @var array<string, EthereumToken> */
    protected array $tokens;
    protected bool $force;
    protected bool $touchEnabled;
    protected int $touchPeriod;
    protected ?WebhookHandlerInterface $webhookHandler;
    /** @var array<EthereumDeposit> */
    protected array $webhooks = [];
    protected int $blockNumber;

    public function __construct(EthereumAddress $address, bool $force = false)
    {
        $this->address = $address;
        $this->force = $force;

        $this->wallet = $address->wallet;
        $this->node = $this->wallet->node ?? Ethereum::getNode();
        $this->nodeApi = $this->node->api();
        $this->explorer = $this->wallet->explorer ?? Ethereum::getExplorer();
        $this->explorerApi = $this->explorer->api();

        /** @var class-string<EthereumToken> $model */
        $model = Ethereum::getModel(EthereumModel::Token);
        $this->tokens = $model::query()
            ->get()
            ->keyBy(fn(EthereumToken $item) => Str::lower($item->address))
            ->all();

        $this->touchEnabled = (bool)config('ethereum.touch.enabled', false);
        $this->touchPeriod = (int)config('ethereum.touch.waiting_seconds', 3600);

        $webhookHandler = config('ethereum.webhook_handler');
        $this->webhookHandler = $webhookHandler ? App::make($webhookHandler) : null;

        $this->blockNumber = $this->node->getLatestBlockNumber();
    }

    public function run(): void
    {
        parent::run();

        if( !$this->address->available ) {
            $this->log('No synchronization required, the address has not been available!', 'success');
            return;
        }

        if ($this->touchEnabled && !$this->force && $this->shouldSkipBySchedule()) {
            $this->log('No full synchronization by the adaptive touch schedule; reconciling pending only.', 'success');
            $this->reconcilePending();
            return;
        }

        $this
            ->balance()
            ->tokenBalances()
            ->transactions()
            ->reconcilePending()
            ->runWebhooks();
    }

    /**
     * Resolve broadcast-but-still-pending outgoing transfers so a stuck/replaced/dropped
     * transaction stops being subtracted from the available balance forever.
     *
     *  - nonce already below the confirmed account nonce: the slot was settled, so the
     *    transfer was mined (stamp block_number) or replaced/dropped (mark dropped_at).
     *  - nonce still next (>= confirmed) or never recorded (legacy rows with nonce = null):
     *    ask the node whether it still knows the txid — a mined block stamps it, an unknown
     *    txid (after a short grace) means it was evicted from the mempool and is dropped,
     *    otherwise it is genuinely stuck and kept.
     *  - `ttl_minutes` remains a last-resort fallback.
     */
    protected function reconcilePending(): static
    {
        $pending = EthereumTransaction::query()
            ->pendingOutgoing()
            ->where('address', $this->address->address)
            ->get();

        if ($pending->isEmpty()) {
            return $this;
        }

        $confirmedNonce = $this->nodeApi->getConfirmedNonce($this->address->address);

        $now = Date::now();

        $ttlMinutes = config('ethereum.pending.ttl_minutes');
        $ttlThreshold = $ttlMinutes !== null ? $now->copy()->subMinutes((int) $ttlMinutes) : null;

        $dropGraceSeconds = (int) config('ethereum.pending.dropped_grace_seconds', 60);
        $dropGraceThreshold = $now->copy()->subSeconds($dropGraceSeconds);

        foreach ($pending as $transaction) {
            if ($transaction->nonce !== null && $transaction->nonce < $confirmedNonce) {
                $receipt = $this->nodeApi->getTransactionReceipt($transaction->txid);

                $transaction->update($receipt !== null
                    ? ['block_number' => $receipt['blockNumber'], 'failed' => $receipt['failed']]
                    : ['dropped_at' => $now]);

                continue;
            }

            // Either the nonce is still next (>= confirmed) or it was never recorded
            // (legacy rows have nonce = null). Ask the node directly whether it still knows
            // the txid: a mined block stamps it, an unknown txid (past the grace) was
            // evicted from the mempool, otherwise it is still in flight and kept.
            $known = $this->nodeApi->getTransactionByHash($transaction->txid);

            if ($known !== null && $known['blockNumber'] !== null) {
                $receipt = $this->nodeApi->getTransactionReceipt($transaction->txid);
                $transaction->update([
                    'block_number' => $known['blockNumber'],
                    'failed' => $receipt['failed'] ?? false,
                ]);

                continue;
            }

            if ($known === null
                && (! $transaction->time_at || $transaction->time_at < $dropGraceThreshold)) {
                $transaction->update(['dropped_at' => $now]);

                continue;
            }

            if ($ttlThreshold !== null && $transaction->time_at && $transaction->time_at < $ttlThreshold) {
                $transaction->update(['dropped_at' => $now]);
            }
        }

        return $this;
    }

    /**
     * Adaptive touch schedule: an address is "active" for `waiting_seconds` after its last
     * touch (user/merchant activity). While active it syncs no more often than `fast_interval`;
     * while idle, no more often than `slow_interval` (null = skip idle addresses entirely).
     */
    protected function shouldSkipBySchedule(): bool
    {
        $now = Date::now();

        $activeWindow = (int) config('ethereum.touch.waiting_seconds', 3600);
        $isActive = ! $this->address->touch_at
            || $this->address->touch_at >= $now->copy()->subSeconds($activeWindow);

        if ($isActive) {
            $interval = (int) config('ethereum.touch.fast_interval', 0);
        } else {
            $slowInterval = config('ethereum.touch.slow_interval');

            if ($slowInterval === null) {
                return true;
            }

            $interval = (int) $slowInterval;
        }

        return $interval > 0
            && $this->address->sync_at
            && $this->address->sync_at >= $now->copy()->subSeconds($interval);
    }

    protected function balance(): static
    {
        $this->log('Starting ETH Balance of address *'.$this->address->address.'* ...');
        $balance = $this->node->getBalance($this->address);
        $this->log('Finished ETH Balance of address *'.$this->address->address.'*: '.$balance->__toString());

        $this->address->update([
            'balance' => $balance,
            'touch_at' => $this->address->touch_at ?: Date::now(),
            'sync_at' => Date::now(),
        ]);

        return $this;
    }

    protected function tokenBalances(): static
    {
        $tokensBalances = [];

        foreach ($this->tokens as $token) {
            $this->log('Get ERC-20 Balance from contract *'.$token->address.'* started...');
            $balance = $this->node->getBalanceOfToken($this->address, $token);
            $this->log(
                'Get ERC-20 Balance from contract *'.$token->address.'* finished: '.$balance->__toString(),
                'success'
            );

            $tokensBalances[$token->address] = $balance->__toString();
        }

        $this->address->update([
            'tokens' => $tokensBalances,
            'sync_at' => Date::now(),
        ]);

        return $this;
    }

    /**
     * Counts an explorer request and the Compute Units it costs.
     */
    protected function explorerOnRequest(?EthereumExplorer $explorer = null, ?ExplorerApiInterface $api = null): \Closure
    {
        $explorer ??= $this->explorer;
        $credits = ($api ?? $this->explorerApi)->creditsPerRequest();

        return function () use ($explorer, $credits): void {
            $explorer->increment('requests');
            $explorer->recordCredits($credits);
        };
    }

    protected function transactions(): static
    {
        // Read first: a failed request leaves nothing half-written and the next sync retries
        $internal = $this->fetchSeparateInternalTransactions($this->address->sync_block_number ?? 0);

        $paginator = $this->explorerApi->getTransactionsPaginator(
            address: $this->address->address,
            startBlock: $this->address->sync_block_number ?? 0,
            perPage: 100,
            callback: $this->explorerOnRequest()
        );

        /** @var TransactionDTO $item */
        foreach ($paginator as $item) {
            $this->checkCancelled();

            if ($item->isInternal()) {
                $internal[] = $item;

                continue;
            }

            $this->handleTransaction($item);
            $this->reportProgress('transactions');
        }

        $this->storeInternalTransactions($internal);

        $this->address->update([
            'sync_at' => Date::now(),
        ]);

        $paginator = $this->explorerApi->getTokenTransactionsPaginator(
            address: $this->address->address,
            contract: null,
            startBlock: $this->address->sync_block_number ?? 0,
            perPage: 100,
            callback: $this->explorerOnRequest()
        );

        /** @var TokenTransactionDTO $item */
        foreach ($paginator as $item) {
            $this->checkCancelled();
            $this->handleTokenTransaction($item);
            $this->reportProgress('token_transactions');
        }

        /*
         * Explorer indexes transactions with a delay: a transaction already mined by the
         * node may not be returned by the explorer yet. Keep an overlap of `lag_blocks`
         * behind the node head so the next sync re-checks recent blocks instead of
         * skipping them forever. Re-processing is idempotent (updateOrCreate by txid).
         */
        $lagBlocks = (int) config('ethereum.sync.lag_blocks', 20);

        $this->address->update([
            'sync_at' => Date::now(),
            'sync_block_number' => max(0, $this->blockNumber - $lagBlocks),
        ]);

        return $this;
    }

    protected function handleTransaction(TransactionDTO $transaction): void
    {
        $type = $transaction->to() === Str::lower($this->address->address) ?
            TransactionType::INCOMING : TransactionType::OUTGOING;

        if( $transaction->contractAddress() || $transaction->isError() ) {
            return;
        }

        EthereumTransaction::updateOrCreate([
            'txid' => $transaction->hash(),
            'address' => $this->address->address,
            'internal' => false,
        ], [
            'type' => $type,
            'time_at' => $transaction->time(),
            'from' => $transaction->from(),
            'to' => $transaction->to(),
            'amount' => $transaction->amount(),
            'token_address' => null,
            'block_number' => $transaction->blockNumber(),
            'data' => $transaction->toArray(),
        ]);

        if( $type === TransactionType::INCOMING ) {
            $deposit = $this->address
                ->deposits()
                ->updateOrCreate([
                    'txid' => $transaction->hash(),
                ], [
                    'wallet_id' => $this->address->wallet_id,
                    'amount' => $transaction->amount(),
                    'block_number' => $transaction->blockNumber(),
                    'confirmations' => $this->blockNumber > $transaction->blockNumber() ? $this->blockNumber - $transaction->blockNumber() : 0,
                    'time_at' => $transaction->time(),
                ]);

            if ($deposit->wasRecentlyCreated) {
                $deposit->setRelation('wallet', $this->wallet);
                $deposit->setRelation('address', $this->address);

                $this->webhooks[] = $deposit;
            }
        }
    }

    protected function handleTokenTransaction(TokenTransactionDTO $transaction): void
    {
        $token = $this->tokens[$transaction->contractAddress()] ?? null;
        if( !$token ) {
            return;
        }

        $type = $transaction->to() === Str::lower($this->address->address) ?
            TransactionType::INCOMING : TransactionType::OUTGOING;

         EthereumTransaction::updateOrCreate([
            'txid' => $transaction->hash(),
            'address' => $this->address->address,
            'internal' => false,
        ], [
            'type' => $type,
            'time_at' => $transaction->time(),
            'from' => $transaction->from(),
            'to' => $transaction->to(),
            'amount' => $transaction->amount(),
            'token_address' => $transaction->contractAddress(),
            'block_number' => $transaction->blockNumber(),
            'data' => $transaction->toArray(),
        ]);

        if( $type == TransactionType::INCOMING ) {
            /** @var EthereumDeposit $deposit */
            $deposit = $this->address
                ->deposits()
                ->updateOrCreate([
                    'txid' => $transaction->hash(),
                ], [
                    'wallet_id' => $this->address->wallet_id,
                    'token_id' => $token->id,
                    'amount' => $transaction->amount(),
                    'block_number' => $transaction->blockNumber(),
                    'confirmations' => $transaction->confirmations(),
                    'time_at' => $transaction->time(),
                ]);

            if ($deposit->wasRecentlyCreated) {
                $deposit->setRelation('wallet', $this->wallet);
                $deposit->setRelation('address', $this->address);
                $deposit->setRelation('token', $token);

                $this->webhooks[] = $deposit;
            }
        }
    }

    /**
     * Reads internal transfers once more from `$fromBlock` (those synced before they were
     * supported) without moving the sync cursor. Deposits found here are history: the webhook
     * handler is only called with `$webhooks`. Returns the number of stored transfers.
     */
    public function backfillInternalTransactions(int $fromBlock = 0, bool $webhooks = false): int
    {
        // Not a run(): start the clock of the log here
        parent::run();

        $source = $this->internalTransactionsSource();

        if ($source === null) {
            $this->log('No explorer returns internal transfers: add an Etherscan API V2 explorer to see them.', 'warn');

            return 0;
        }

        [$explorer, $api] = $source;
        $this->webhooks = [];
        $items = [];

        foreach ($api->getInternalTransactionsPaginator($this->address->address, $fromBlock, 100, $this->explorerOnRequest($explorer, $api)) as $item) {
            $this->checkCancelled();
            $items[] = $item;
        }

        $stored = $this->storeInternalTransactions($items);

        if ($webhooks) {
            $this->runWebhooks();
        }

        $this->webhooks = [];

        return $stored;
    }

    /**
     * Internal transfers when the explorer does not return them with the transactions: from
     * the explorer itself (Etherscan txlistinternal) or from another explorer that knows them.
     *
     * @return array<TransactionDTO>
     */
    protected function fetchSeparateInternalTransactions(int $startBlock): array
    {
        if (!config('ethereum.sync.internal_transactions', true)) {
            return [];
        }

        if ($this->explorerApi instanceof InternalTransactionsInterface && $this->explorerApi->combinesInternalTransactions()) {
            return [];
        }

        $source = $this->internalTransactionsSource();

        if ($source === null) {
            $this->log('No explorer returns internal transfers: add an Etherscan API V2 explorer to see them.', 'warn');

            return [];
        }

        [$explorer, $api] = $source;
        $items = [];

        foreach ($api->getInternalTransactionsPaginator($this->address->address, $startBlock, 100, $this->explorerOnRequest($explorer, $api)) as $item) {
            $this->checkCancelled();
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array{0: EthereumExplorer, 1: ExplorerApiInterface&InternalTransactionsInterface}|null
     */
    protected function internalTransactionsSource(): ?array
    {
        if ($this->explorerApi instanceof InternalTransactionsInterface && $this->explorerApi->supportsInternalTransactions()) {
            return [$this->explorer, $this->explorerApi];
        }

        $others = Ethereum::getModel(EthereumModel::Explorer)::query()
            ->where('worked', '=', true)
            ->where('available', '=', true)
            ->whereKeyNot($this->explorer->getKey())
            ->orderByCredits()
            ->orderBy('requests')
            ->get();

        foreach ($others as $explorer) {
            $api = $explorer->api();

            if ($api instanceof InternalTransactionsInterface && $api->supportsInternalTransactions()) {
                return [$explorer, $api];
            }
        }

        return null;
    }

    /**
     * One row per transaction and direction: a transaction may send the address several
     * internal transfers, they are summed. Failed calls, zero values and contract
     * creations are not transfers.
     *
     * @param array<TransactionDTO> $items
     */
    protected function storeInternalTransactions(array $items): int
    {
        $own = Str::lower($this->address->address);
        $groups = [];

        foreach ($items as $item) {
            if ($item->isError() || $item->contractAddress() || !$item->amount()->isPositive()) {
                continue;
            }

            $type = match ($own) {
                $item->to() => TransactionType::INCOMING,
                $item->from() => TransactionType::OUTGOING,
                default => null,
            };

            if ($type === null) {
                continue;
            }

            $key = $item->hash().'|'.$type->value;

            if (isset($groups[$key])) {
                $groups[$key]['amount'] = $groups[$key]['amount']->plus($item->amount());
            } else {
                $groups[$key] = ['type' => $type, 'item' => $item, 'amount' => $item->amount()];
            }
        }

        foreach ($groups as $group) {
            $this->handleInternalTransaction($group['item'], $group['type'], $group['amount']);
            $this->reportProgress('internal_transactions');
        }

        return count($groups);
    }

    protected function handleInternalTransaction(TransactionDTO $transaction, TransactionType $type, \Brick\Math\BigDecimal $amount): void
    {
        EthereumTransaction::updateOrCreate([
            'txid' => $transaction->hash(),
            'address' => $this->address->address,
            'internal' => true,
        ], [
            'type' => $type,
            'time_at' => $transaction->time(),
            'from' => $transaction->from(),
            'to' => $transaction->to(),
            'amount' => $amount,
            'token_address' => null,
            'block_number' => $transaction->blockNumber(),
            'data' => $transaction->toArray(),
        ]);

        if ($type !== TransactionType::INCOMING) {
            return;
        }

        $deposit = $this->address
            ->deposits()
            ->updateOrCreate([
                'txid' => $transaction->hash(),
            ], [
                'wallet_id' => $this->address->wallet_id,
                'amount' => $amount,
                'block_number' => $transaction->blockNumber(),
                'confirmations' => max(0, $this->blockNumber - $transaction->blockNumber()),
                'time_at' => $transaction->time(),
            ]);

        if ($deposit->wasRecentlyCreated) {
            $deposit->setRelation('wallet', $this->wallet);
            $deposit->setRelation('address', $this->address);

            $this->webhooks[] = $deposit;
        }
    }

    protected function runWebhooks(): static
    {
        if( $this->webhookHandler ) {
            foreach( $this->webhooks as $item ) {
                try {
                    $this->log('Call Webhook Handler for Deposit #'.$item->id.'...');
                    $this->webhookHandler->handle($item);
                    $this->log('Successfully', 'success');
                }
                catch(\Exception $e) {
                    $this->log('Error: '.$e->getMessage(), 'error');
                }
            }
        }

        return $this;
    }
}