<?php

namespace ItHealer\LaravelEthereum\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Jobs\SyncEthereumAddressJob;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumAlchemyWebhook;
use ItHealer\LaravelEthereum\Services\Alchemy\AlchemySignature;

/**
 * Receives Alchemy Address Activity notifications: verifies the signature, resolves the
 * involved watched addresses and dispatches a targeted sync for each.
 */
class AlchemyWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $data = json_decode($payload, true);

        $webhookId = $data['webhookId'] ?? null;

        if (!is_array($data) || !$webhookId) {
            abort(400, 'Invalid payload.');
        }

        /** @var class-string<EthereumAlchemyWebhook> $webhookModel */
        $webhookModel = Ethereum::getModel(EthereumModel::AlchemyWebhook);

        /** @var EthereumAlchemyWebhook|null $webhook */
        $webhook = $webhookModel::query()->where('webhook_id', $webhookId)->first();

        if (!$webhook) {
            abort(404, 'Unknown webhook.');
        }

        if (!AlchemySignature::isValid($payload, $request->header('X-Alchemy-Signature'), $webhook->signing_key)) {
            abort(403, 'Invalid signature.');
        }

        // Match both sides: the address may be the recipient (incoming) or sender (outgoing).
        $candidates = collect($data['event']['activity'] ?? [])
            ->flatMap(fn (array $activity) => [$activity['fromAddress'] ?? null, $activity['toAddress'] ?? null])
            ->filter()
            ->map(fn (string $address) => Str::lower($address))
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return response()->json(['handled' => 0]);
        }

        /** @var class-string<EthereumAddress> $addressModel */
        $addressModel = Ethereum::getModel(EthereumModel::Address);

        $addresses = $addressModel::query()
            ->whereIn(DB::raw('LOWER(address)'), $candidates->all())
            ->get();

        foreach ($addresses as $address) {
            SyncEthereumAddressJob::dispatch($address);
        }

        return response()->json(['handled' => $addresses->count()]);
    }
}
