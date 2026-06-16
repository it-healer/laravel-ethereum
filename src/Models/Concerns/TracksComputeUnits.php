<?php

namespace ItHealer\LaravelEthereum\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Meters provider credits (Alchemy Compute Units, Infura credits) spent on a node/explorer in a
 * `credits` counter. The reset period depends on the provider detected from base_url: Alchemy
 * resets monthly, Infura resets daily ("Daily Credits"). Selection orders by least credits in the
 * current period, so load is distributed across several nodes/explorers automatically.
 *
 * @property int $credits
 * @property \Illuminate\Support\Carbon|null $credits_at
 * @property string|null $base_url
 */
trait TracksComputeUnits
{
    public function recordCredits(int $credits): void
    {
        if ($credits <= 0 || ! $this->exists) {
            return;
        }

        $now = Date::now();

        if (! $this->credits_at || $this->credits_at->lt($this->creditPeriodStart($now))) {
            $this->forceFill(['credits' => $credits, 'credits_at' => $now])->saveQuietly();

            return;
        }

        $this->increment('credits', $credits, ['credits_at' => $now]);
    }

    /**
     * Credits spent in the current provider period (a stale period counts as zero).
     */
    public function creditsThisPeriod(): int
    {
        if (! $this->credits_at || $this->credits_at->lt($this->creditPeriodStart(Date::now()))) {
            return 0;
        }

        return (int) $this->credits;
    }

    /**
     * Backwards-compatible alias of creditsThisPeriod().
     */
    public function creditsThisMonth(): int
    {
        return $this->creditsThisPeriod();
    }

    /**
     * Provider detected from base_url: 'infura', 'alchemy' or null.
     */
    public function creditProvider(): ?string
    {
        $url = (string) $this->base_url;

        if (str_contains($url, 'infura.io')) {
            return 'infura';
        }

        if (str_contains($url, 'g.alchemy.com')) {
            return 'alchemy';
        }

        return null;
    }

    /**
     * Credit reset period: 'day' for Infura (Daily Credits), 'month' otherwise (Alchemy CU).
     */
    public function creditPeriod(): string
    {
        return $this->creditProvider() === 'infura' ? 'day' : 'month';
    }

    protected function creditPeriodStart(Carbon $now): Carbon
    {
        return $this->creditPeriod() === 'day'
            ? $now->copy()->startOfDay()
            : $now->copy()->startOfMonth();
    }

    public function scopeOrderByCredits(Builder $query): Builder
    {
        $now = Date::now();

        return $query->orderByRaw(
            'case
                when credits_at is null then 0
                when base_url like ? then (case when credits_at < ? then 0 else credits end)
                else (case when credits_at < ? then 0 else credits end)
            end asc',
            [
                '%infura.io%',
                $now->copy()->startOfDay()->toDateTimeString(),
                $now->copy()->startOfMonth()->toDateTimeString(),
            ]
        );
    }
}
