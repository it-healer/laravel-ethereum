<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use ItHealer\LaravelEthereum\Casts\BigDecimalCast;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Enums\TransactionType;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class EthereumTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'txid',
        'address',
        'type',
        'time_at',
        'from',
        'to',
        'amount',
        'token_address',
        'block_number',
        'data',
    ];

    protected $appends = [
        'symbol',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'time_at' => 'datetime',
            'amount' => BigDecimalCast::class,
            'block_number' => 'integer',
            'data' => 'array',
        ];
    }

    public function addresses(): HasMany
    {
        /** @var class-string<EthereumAddress> $model */
        $model = Ethereum::getModel(EthereumModel::Address);

        return $this->hasMany($model, 'address', 'address');
    }

    public function wallets(): HasManyThrough
    {
        /** @var class-string<EthereumWallet> $walletModel */
        $walletModel = Ethereum::getModel(EthereumModel::Wallet);

        /** @var class-string<EthereumAddress> $addressModel */
        $addressModel = Ethereum::getModel(EthereumModel::Address);

        return $this->hasManyThrough(
            $walletModel,
            $addressModel,
            'address',
            'id',
            'address',
            'wallet_id'
        );
    }

    public function token(): BelongsTo
    {
        /** @var class-string<EthereumToken> $model */
        $model = Ethereum::getModel(EthereumModel::Token);

        return $this->belongsTo($model, 'token_address', 'address');
    }

    protected function symbol(): Attribute
    {
        return new Attribute(
            get: fn () => $this->token_address ? ($this->token?->symbol ?: 'TOKEN') : 'ETH'
        );
    }
}
