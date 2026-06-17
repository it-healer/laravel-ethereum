<?php

namespace ItHealer\LaravelEthereum\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumTransaction;

/**
 * Computes the in-flight value of broadcast-but-not-yet-mined outgoing transfers,
 * so the UI can show a truthful "available" balance immediately after a withdrawal
 * instead of the stale confirmed balance.
 */
class PendingBalance
{
    /**
     * Sum of pending outgoing transfers per address, in one query.
     *
     * @param  string[]  $addresses
     * @return array<string, array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}>
     *                                Keyed by lowercased address.
     */
    public static function forAddresses(array $addresses): array
    {
        $addresses = array_values(array_unique(array_map(
            fn (string $address) => Str::lower($address),
            $addresses
        )));

        if ($addresses === []) {
            return [];
        }

        /** @var class-string<EthereumTransaction> $model */
        $model = Ethereum::getModel(EthereumModel::Transaction);

        $placeholders = implode(',', array_fill(0, count($addresses), '?'));

        $rows = $model::query()
            ->pendingOutgoing()
            ->whereRaw("LOWER(address) IN ($placeholders)", $addresses)
            ->selectRaw('LOWER(address) as address_key, token_address, SUM(amount) as amount_sum, SUM(fee) as fee_sum')
            ->groupBy('address_key', 'token_address')
            ->toBase()
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $key = $row->address_key;
            $result[$key] ??= ['native' => BigDecimal::zero(), 'fee' => BigDecimal::zero(), 'tokens' => []];

            $result[$key]['fee'] = $result[$key]['fee']->plus((string) ($row->fee_sum ?? '0'));

            $contract = (string) ($row->token_address ?? '');
            $amount = BigDecimal::of((string) ($row->amount_sum ?? '0'));

            if ($contract === '') {
                $result[$key]['native'] = $result[$key]['native']->plus($amount);
            } else {
                $result[$key]['tokens'][$contract] = ($result[$key]['tokens'][$contract] ?? BigDecimal::zero())->plus($amount);
            }
        }

        return $result;
    }

    /**
     * @return array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}
     */
    public static function forAddress(string $address): array
    {
        return static::forAddresses([$address])[Str::lower($address)]
            ?? ['native' => BigDecimal::zero(), 'fee' => BigDecimal::zero(), 'tokens' => []];
    }

    /**
     * @param  array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}  $pending
     */
    public static function availableNative(BigDecimal $balance, array $pending): BigDecimal
    {
        $available = $balance->minus($pending['native'])->minus($pending['fee']);

        return $available->isNegative() ? BigDecimal::zero() : $available;
    }

    /**
     * @param  array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}  $pending
     */
    public static function availableToken(string $contract, BigDecimal|string|int|float|null $tokenBalance, array $pending): BigDecimal
    {
        $available = BigDecimal::of($tokenBalance ?? 0)->minus($pending['tokens'][$contract] ?? BigDecimal::zero());

        return $available->isNegative() ? BigDecimal::zero() : $available;
    }
}
