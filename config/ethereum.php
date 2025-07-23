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
