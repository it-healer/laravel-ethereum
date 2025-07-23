<?php

namespace ItHealer\LaravelEthereum\Webhook;

use ItHealer\LaravelEthereum\Models\EthereumDeposit;

interface WebhookHandlerInterface
{
    public function handle(EthereumDeposit $deposit): void;
}