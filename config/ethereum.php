<?php

return [
    /*
     * Touch Synchronization System (TSS) config
     * If there are many addresses in the system, we synchronize only those that have been touched recently.
     * You must update touch_at in EthereumAddress, if you want sync here.
     */
    'touch' => [
        /*
         * Is system enabled?
         */
        'enabled' => false,

        /*
         * The time during which the address is synchronized after touching it (in seconds).
         */
        'waiting_seconds' => 3600,
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
    ],
];
