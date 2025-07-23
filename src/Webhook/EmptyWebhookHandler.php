<?php

namespace ItHealer\LaravelEthereum\Webhook;

use Illuminate\Support\Facades\Log;
use ItHealer\LaravelEthereum\Models\EthereumDeposit;

class EmptyWebhookHandler implements WebhookHandlerInterface
{
    public function handle(EthereumDeposit $deposit): void
    {
        Log::error('NEW DEPOSIT FOR ADDRESS '.$deposit->address->address.' = '.$deposit->txid);
    }
}