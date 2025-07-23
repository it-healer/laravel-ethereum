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
        'tokens_balances'
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
