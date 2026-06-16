<?php

namespace ItHealer\LaravelEthereum\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumAlchemyWebhook;
use ItHealer\LaravelEthereum\Services\Alchemy\AlchemyNotifyClient;
use ItHealer\LaravelEthereum\Services\AlchemyUrlFactory;

/**
 * High-level management of the Alchemy Address Activity webhook (single Ethereum network).
 */
trait Alchemy
{
    /**
     * Notify client for a specific account token, or the configured default when null.
     * The webhook stores the token of the account it was created on, so operations always
     * target the right Alchemy account even when several accounts are configured.
     */
    public function alchemyNotify(?string $authToken = null): AlchemyNotifyClient
    {
        if ($authToken === null || $authToken === '') {
            return app(AlchemyNotifyClient::class);
        }

        return new AlchemyNotifyClient(
            authToken: $authToken,
            apiUrl: (string) config('ethereum.alchemy.api_url', 'https://dashboard.alchemy.com/api'),
            proxy: config('ethereum.proxy'),
        );
    }

    public function findAlchemyWebhook(): ?EthereumAlchemyWebhook
    {
        /** @var class-string<EthereumAlchemyWebhook> $model */
        $model = $this->getModel(EthereumModel::AlchemyWebhook);

        return $model::query()->first();
    }

    public function ensureAlchemyWebhook(?string $authToken = null, ?string $accountRef = null): EthereumAlchemyWebhook
    {
        if ($webhook = $this->findAlchemyWebhook()) {
            return $webhook;
        }

        $chainId = (int) config('ethereum.explorer.chain_id', 1);
        $alchemyNetwork = AlchemyUrlFactory::network($chainId);

        if (!$alchemyNetwork) {
            throw new \InvalidArgumentException("Alchemy Notify does not support chain id {$chainId}.");
        }

        $result = $this->alchemyNotify($authToken)->createWebhook($alchemyNetwork, $this->alchemyWebhookUrl());

        /** @var class-string<EthereumAlchemyWebhook> $model */
        $model = $this->getModel(EthereumModel::AlchemyWebhook);

        return $model::create([
            'webhook_id' => $result['id'],
            'signing_key' => $result['signing_key'],
            'auth_token' => $authToken,
            'account_ref' => $accountRef,
            'addresses_count' => 0,
            'active' => true,
        ]);
    }

    /**
     * Add an address to the Alchemy webhook. No-op if no webhook exists yet (it is provisioned
     * explicitly via ensureAlchemyWebhook with a chosen account).
     */
    public function subscribeAlchemyAddress(EthereumAddress|string $address): void
    {
        $webhook = $this->findAlchemyWebhook();

        if (!$webhook) {
            return;
        }

        $value = $address instanceof EthereumAddress ? $address->address : $address;

        $this->alchemyNotify($webhook->auth_token)->updateAddresses($webhook->webhook_id, add: [$value]);
        $webhook->increment('addresses_count');
    }

    public function unsubscribeAlchemyAddress(EthereumAddress|string $address): void
    {
        $webhook = $this->findAlchemyWebhook();

        if (!$webhook) {
            return;
        }

        $value = $address instanceof EthereumAddress ? $address->address : $address;

        $this->alchemyNotify($webhook->auth_token)->updateAddresses($webhook->webhook_id, remove: [$value]);
        $webhook->decrement('addresses_count');
    }

    /**
     * Reconcile the Alchemy watched-address list with the available addresses the package tracks.
     *
     * @return array{added: list<string>, removed: list<string>}
     */
    public function reconcileAlchemyWebhook(?string $authToken = null, ?string $accountRef = null): array
    {
        $webhook = $this->ensureAlchemyWebhook($authToken, $accountRef);
        $notify = $this->alchemyNotify($webhook->auth_token);

        $local = $this->alchemyTrackedAddresses();
        $localLower = $local->map(fn (string $a) => Str::lower($a));

        $remote = collect($notify->getAddresses($webhook->webhook_id));
        $remoteLower = $remote->map(fn (string $a) => Str::lower($a));

        $add = $local->filter(fn (string $a) => !$remoteLower->contains(Str::lower($a)))->values();
        $remove = $remote->filter(fn (string $a) => !$localLower->contains(Str::lower($a)))->values();

        if ($add->isNotEmpty() || $remove->isNotEmpty()) {
            $notify->updateAddresses($webhook->webhook_id, $add->all(), $remove->all());
        }

        $webhook->update(['addresses_count' => $local->count()]);

        return ['added' => $add->all(), 'removed' => $remove->all()];
    }

    /**
     * @return Collection<int, string>
     */
    public function alchemyTrackedAddresses(): Collection
    {
        /** @var class-string<EthereumAddress> $model */
        $model = $this->getModel(EthereumModel::Address);

        return $model::query()->where('available', true)->pluck('address');
    }

    protected function alchemyWebhookUrl(): string
    {
        $url = config('ethereum.alchemy.webhook.url');

        if ($url) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim((string) config('ethereum.alchemy.webhook.path'), '/');
    }
}
