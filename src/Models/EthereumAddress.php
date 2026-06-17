<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItHealer\LaravelEthereum\Casts\BigDecimalCast;
use ItHealer\LaravelEthereum\Casts\EncryptedCast;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class EthereumAddress extends Model
{
    protected $fillable = [
        'wallet_id',
        'address',
        'title',
        'watch_only',
        'private_key',
        'index',
        'balance',
        'tokens',
        'touch_at',
        'sync_at',
        'sync_block_number',
        'available',
    ];

    protected $appends = [
        'tokens_balances',
        'available_balance',
        'available_tokens_balances',
    ];

    protected $hidden = [
        'private_key',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'watch_only' => 'boolean',
            'private_key' => EncryptedCast::class,
            'balance' => BigDecimalCast::class,
            'tokens' => 'array',
            'touch_at' => 'datetime',
            'sync_at' => 'datetime',
            'sync_block_number' => 'integer',
            'available' => 'boolean',
        ];
    }

    public function getPlainPasswordAttribute(): ?string
    {
        return $this->wallet->plain_password;
    }

    public function getPasswordAttribute(): ?string
    {
        return $this->wallet->password;
    }

    protected function tokensBalances(): Attribute
    {
        /** @var class-string<EthereumToken> $model */
        $model = Ethereum::getModel(EthereumModel::Token);

        return new Attribute(
            get: fn () => $model::get()->map(fn (Model $token) => [
                ...$token->only(['address', 'name', 'symbol', 'decimals']),
                'balance' => $this->tokens[$token->address] ?? null,
            ])->keyBy('address')
        );
    }

    /**
     * Native balance minus broadcast-but-unconfirmed outgoing transfers (amount + fees),
     * so a withdrawal is reflected immediately, before the chain confirms it.
     */
    protected function availableBalance(): Attribute
    {
        return new Attribute(
            get: fn (): string => (string) \ItHealer\LaravelEthereum\Services\PendingBalance::availableNative(
                \Brick\Math\BigDecimal::of($this->balance ?? 0),
                \ItHealer\LaravelEthereum\Services\PendingBalance::forAddress((string) $this->address)
            )
        );
    }

    /**
     * Token balances (same shape as tokens_balances) reduced by pending outgoing token transfers.
     */
    protected function availableTokensBalances(): Attribute
    {
        /** @var class-string<EthereumToken> $model */
        $model = Ethereum::getModel(EthereumModel::Token);

        return new Attribute(
            get: function () use ($model) {
                $pending = \ItHealer\LaravelEthereum\Services\PendingBalance::forAddress((string) $this->address);

                return $model::get()->map(fn (Model $token) => [
                    ...$token->only(['address', 'name', 'symbol', 'decimals']),
                    'balance' => ($this->tokens[$token->address] ?? null) !== null
                        ? (string) \ItHealer\LaravelEthereum\Services\PendingBalance::availableToken($token->address, $this->tokens[$token->address], $pending)
                        : null,
                ])->keyBy('address');
            }
        );
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(EthereumWallet::class);
    }

    public function deposits(): HasMany
    {
        /** @var class-string<EthereumDeposit> $model */
        $model = Ethereum::getModel(EthereumModel::Deposit);

        return $this->hasMany($model, 'address_id');
    }

    public function transactions(): HasMany
    {
        /** @var class-string<EthereumTransaction> $model */
        $model = Ethereum::getModel(EthereumModel::Transaction);

        return $this->hasMany($model, 'address', 'address');
    }
}
