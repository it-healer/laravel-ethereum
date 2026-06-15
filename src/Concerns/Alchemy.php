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
    public function alchemyNotify(): AlchemyNotifyClient
    {
        return app(AlchemyNotifyClient::class);
    }

    public function findAlchemyWebhook(): ?EthereumAlchemyWebhook
    {
        /** @var class-string<EthereumAlchemyWebhook> $model */
        $model = $this->getModel(EthereumModel::AlchemyWebhook);

        return $model::query()->first();
    }

    public function ensureAlchemyWebhook(): EthereumAlchemyWebhook
    {
        if ($webhook = $this->findAlchemyWebhook()) {
            return $webhook;
        }

        $chainId = (int) config('ethereum.explorer.chain_id', 1);
        $alchemyNetwork = AlchemyUrlFactory::network($chainId);

        if (!$alchemyNetwork) {
            throw new \InvalidArgumentException("Alchemy Notify does not support chain id {$chainId}.");
        }

        $result = $this->alchemyNotify()->createWebhook($alchemyNetwork, $this->alchemyWebhookUrl());

        /** @var class-string<EthereumAlchemyWebhook> $model */
        $model = $this->getModel(EthereumModel::AlchemyWebhook);

        return $model::create([
            'webhook_id' => $result['id'],
            'signing_key' => $result['signing_key'],
            'addresses_count' => 0,
            'active' => true,
        ]);
    }

    public function subscribeAlchemyAddress(EthereumAddress|string $address): void
    {
        $webhook = $this->ensureAlchemyWebhook();
        $value = $address instanceof EthereumAddress ? $address->address : $address;

        $this->alchemyNotify()->updateAddresses($webhook->webhook_id, add: [$value]);
        $webhook->increment('addresses_count');
    }

    public function unsubscribeAlchemyAddress(EthereumAddress|string $address): void
    {
        $webhook = $this->findAlchemyWebhook();

        if (!$webhook) {
            return;
        }

        $value = $address instanceof EthereumAddress ? $address->address : $address;

        $this->alchemyNotify()->updateAddresses($webhook->webhook_id, remove: [$value]);
        $webhook->decrement('addresses_count');
    }

    /**
     * Reconcile the Alchemy watched-address list with the available addresses the package tracks.
     *
     * @return array{added: list<string>, removed: list<string>}
     */
    public function reconcileAlchemyWebhook(): array
    {
        $webhook = $this->ensureAlchemyWebhook();

        $local = $this->alchemyTrackedAddresses();
        $localLower = $local->map(fn (string $a) => Str::lower($a));

        $remote = collect($this->alchemyNotify()->getAddresses($webhook->webhook_id));
        $remoteLower = $remote->map(fn (string $a) => Str::lower($a));

        $add = $local->filter(fn (string $a) => !$remoteLower->contains(Str::lower($a)))->values();
        $remove = $remote->filter(fn (string $a) => !$localLower->contains(Str::lower($a)))->values();

        if ($add->isNotEmpty() || $remove->isNotEmpty()) {
            $this->alchemyNotify()->updateAddresses($webhook->webhook_id, $add->all(), $remove->all());
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
