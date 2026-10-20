<?php

return [
    /*
     * Touch Synchronization System (TSS) config
     * If there are many addresses in the system, we synchronize only those that have been touched recently.
     * You must update touch_at in EthereumAddress, if you want sync here.
     */
    'touch' => [
        /*
         * Is the adaptive (touch-based) synchronization enabled?
         * When enabled, addresses are synced frequently while in use and rarely while idle.
         */
        'enabled' => false,

        /*
         * Active window: an address is considered "in use" for this many seconds after its
         * last touch (touch_at — set on user/merchant activity).
         */
        'waiting_seconds' => 3600,

        /*
         * Minimum seconds between syncs while the address is active (0 = every run).
         */
        'fast_interval' => 0,

        /*
         * Minimum seconds between syncs while the address is idle.
         * null = skip idle addresses entirely (legacy behavior).
         */
        'slow_interval' => null,
    ],

    /*
     * Explorer API settings.
     */
    'explorer' => [
        /*
         * Chain id sent with every explorer request (required by Etherscan API V2).
         * 1 = Ethereum mainnet. Use the V2 endpoint as explorer base URL:
         * https://api.etherscan.io/v2/api
         */
        'chain_id' => 1,
    ],

    /*
     * Address synchronization settings.
     */
    'sync' => [
        /*
         * Explorers (Etherscan etc.) index transactions with a delay relative to the
         * node head. Each sync keeps this many blocks of overlap behind the current
         * block, so transactions indexed late are still picked up on the next run.
         * ~20 blocks is about 4 minutes of overlap.
         */
        'lag_blocks' => 20,

        /*
         * Track outgoing transfers in addition to incoming ones. On the Alchemy explorer
         * driver, disabling this halves the alchemy_getAssetTransfers requests (and CU).
         */
        'track_outgoing' => (bool) env('ETHEREUM_SYNC_TRACK_OUTGOING', true),

        /*
         * Internal transfers: the ETH a contract sent to the address while executing a
         * transaction (a bridge, an exchange withdrawal, a smart wallet, the ETH of a swap).
         * They are recorded as incoming transactions and deposits like ordinary transfers.
         * Alchemy (Ethereum mainnet) returns them in the same request; Etherscan needs one
         * more request (txlistinternal) per sync.
         */
        'internal_transactions' => (bool) env('ETHEREUM_SYNC_INTERNAL_TRANSACTIONS', true),
    ],

    /*
     * Broadcast-but-unconfirmed outgoing transfers are subtracted from the confirmed
     * balance to show a truthful "available" balance. They leave the pending set once
     * mined or reconciled during sync: a transfer whose nonce is already confirmed was
     * mined or replaced, while a still-next transfer the node no longer knows
     * (eth_getTransactionByHash returns null) was evicted from the mempool. The node is
     * asked only after `dropped_grace_seconds` so a freshly broadcast transfer the node
     * has not yet registered is not dropped prematurely. `ttl_minutes` is a last-resort
     * safety net (null relies solely on node reconciliation).
     */
    'pending' => [
        'ttl_minutes' => env('ETHEREUM_PENDING_TTL_MINUTES') !== null
            ? (int) env('ETHEREUM_PENDING_TTL_MINUTES')
            : null,

        'dropped_grace_seconds' => (int) env('ETHEREUM_PENDING_DROPPED_GRACE_SECONDS', 60),
    ],

    /*
     * Confirmations target used by ethereum:confirm-deposits to decide which deposits
     * still need a top-up sync (relevant when deposits arrive via Alchemy webhooks).
     */
    /*
     * Gas estimate multiplier for arbitrary contract calls (previewCall / sendCall)
     * sent without an explicit gas limit, e.g. dApp requests over WalletConnect.
     */
    'call_gas_buffer' => '1.2',

    /*
     * Gas price of outgoing transactions (legacy): the next block's base fee times
     * `base_fee_multiplier` plus the median tip of recent blocks, at least
     * `min_priority_fee_gwei`. A bare eth_gasPrice has almost no tip and gets stuck for
     * minutes as soon as the base fee rises.
     */
    'gas' => [
        'base_fee_multiplier' => env('ETHEREUM_GAS_BASE_FEE_MULTIPLIER', '1.25'),
        'min_priority_fee_gwei' => env('ETHEREUM_GAS_MIN_PRIORITY_FEE_GWEI', '0.01'),
    ],

    'confirmations_target' => (int) env('ETHEREUM_CONFIRMATIONS_TARGET', 12),

    /*
     * Compute Unit (CU) cost overrides per RPC method (meters node/explorer credits,
     * reset monthly, least-used picked). Defaults mirror Alchemy — see
     * \ItHealer\LaravelEthereum\Services\Alchemy\ComputeUnits.
     */
    'compute_units' => [],

    /*
     * Infura credit cost overrides per RPC method (meters node credits, reset daily,
     * least-used picked). Defaults mirror Infura's credit pricing — see
     * \ItHealer\LaravelEthereum\Services\Infura\InfuraCredits.
     */
    'infura_credits' => [],

    /*
     * Alchemy Notify (Address Activity webhooks). See README for setup.
     */
    'alchemy' => [
        'auth_token' => env('ETHEREUM_ALCHEMY_NOTIFY_AUTH_TOKEN'),

        'api_url' => 'https://dashboard.alchemy.com/api',

        'webhook' => [
            'enabled' => (bool) env('ETHEREUM_ALCHEMY_WEBHOOK_ENABLED', false),
            'path' => env('ETHEREUM_ALCHEMY_WEBHOOK_PATH', 'ethereum/alchemy/webhook'),
            'url' => env('ETHEREUM_ALCHEMY_WEBHOOK_URL'),
            'middleware' => [],
        ],

        'auto_subscribe' => (bool) env('ETHEREUM_ALCHEMY_AUTO_SUBSCRIBE', false),

        'queue' => [
            'connection' => env('ETHEREUM_ALCHEMY_QUEUE_CONNECTION'),
            'name' => env('ETHEREUM_ALCHEMY_QUEUE_NAME'),
        ],
    ],

    /*
     * Wallet settings.
     */
    'wallet' => [
        /*
         * Default BIP-44 derivation path template used when creating a wallet.
         * The {index} placeholder is replaced with the address index.
         * Presets: Ethereum::PATH_BIP44 (MetaMask and most software wallets),
         * Ethereum::PATH_LEDGER_LIVE, Ethereum::PATH_LEDGER_LEGACY.
         */
        'default_derivation_path' => "m/44'/60'/0'/0/{index}",
    ],

    /*
     * Sets the handler to be used when Ethereum Wallet
     * receives a new deposit.
     */
    'webhook_handler' => \ItHealer\LaravelEthereum\Webhook\EmptyWebhookHandler::class,

    /*
     * Set model class for both TronWallet, TronAddress, TronTrc20,
     * to allow more customization.
     *
     * Node model must be or extend `\ItHealer\LaravelEthereum\Models\EthereumNode::class`
     * Explorer model must be or extend `\ItHealer\LaravelEthereum\Models\EthereumExplorer::class`
     * Token model must be or extend `\ItHealer\LaravelEthereum\Models\EthereumToken::class`
     * Wallet model must be or extend `\ItHealer\LaravelEthereum\Models\EthereumWallet:class`
     * Address model must be or extend `\ItHealer\LaravelEthereum\Models\EthereumAddress::class`
     */
    'models' => [
        'node' => \ItHealer\LaravelEthereum\Models\EthereumNode::class,
        'explorer' => \ItHealer\LaravelEthereum\Models\EthereumExplorer::class,
        'token' => \ItHealer\LaravelEthereum\Models\EthereumToken::class,
        'wallet' => \ItHealer\LaravelEthereum\Models\EthereumWallet::class,
        'address' => \ItHealer\LaravelEthereum\Models\EthereumAddress::class,
        'transaction' => \ItHealer\LaravelEthereum\Models\EthereumTransaction::class,
        'deposit' => \ItHealer\LaravelEthereum\Models\EthereumDeposit::class,
        'alchemy_webhook' => \ItHealer\LaravelEthereum\Models\EthereumAlchemyWebhook::class,
    ],

    /*
     * HTTP to nodes and explorers: time to resolve and connect, and how many times a request is repeated
     * when it failed before reaching the server (DNS did not answer, host unreachable) — safe to repeat.
     */
    'http' => [
        'connect_timeout' => (int)env('ETHEREUM_HTTP_CONNECT_TIMEOUT', 20),
        'retries' => (int)env('ETHEREUM_HTTP_RETRIES', 2),
        'retry_sleep_ms' => 500,
    ],
];
