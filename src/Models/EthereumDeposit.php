<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ItHealer\LaravelEthereum\Casts\BigDecimalCast;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class EthereumDeposit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id',
        'address_id',
        'token_id',
        'txid',
        'amount',
        'block_number',
        'confirmations',
        'time_at',
    ];

    protected $appends = [
        'symbol',
    ];

    protected function casts(): array
    {
        return [
            'amount' => BigDecimalCast::class,
            'block_number' => 'integer',
            'confirmations' => 'integer',
            'time_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        /** @var class-string<EthereumWallet> $model */
        $model = Ethereum::getModel(EthereumModel::Wallet);

        return $this->belongsTo($model, 'wallet_id');
    }

    public function address(): BelongsTo
    {
        /** @var class-string<EthereumAddress> $model */
        $model = Ethereum::getModel(EthereumModel::Address);

        return $this->belongsTo($model, 'address_id');
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
            get: fn () => $this->token_id ? ($this->token?->symbol ?: 'TOKEN') : 'ETH'
        );
    }
}
