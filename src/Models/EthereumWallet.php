<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use ItHealer\LaravelEthereum\Casts\EncryptedCast;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;

class EthereumWallet extends Model
{
    protected static array $plainPasswords = [];

    protected $fillable = [
        'node_id',
        'explorer_id',
        'name',
        'title',
        'password',
        'mnemonic',
        'seed',
        'sync_at',
        'balance',
        'tokens',
    ];

    protected $appends = [
        'tokens_balances',
        'has_password',
        'has_mnemonic',
        'has_seed',
    ];

    protected $hidden = [
        'password',
        'mnemonic',
        'seed',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'sync_at' => 'datetime',
            'password' => 'encrypted',
            'mnemonic' => EncryptedCast::class,
            'seed' => EncryptedCast::class,
            'tokens' => 'array',
        ];
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

    public function unlockWallet(?string $password): void
    {
        self::$plainPasswords[$this->name] = $password;
    }

    public function getPlainPasswordAttribute(): ?string
    {
        return self::$plainPasswords[$this->name] ?? null;
    }

    public function node(): BelongsTo
    {
        /** @var class-string<EthereumNode> $model */
        $model = Ethereum::getModel(EthereumModel::Node);

        return $this->belongsTo($model);
    }

    public function explorer(): BelongsTo
    {
        /** @var class-string<EthereumExplorer> $model */
        $model = Ethereum::getModel(EthereumModel::Explorer);

        return $this->belongsTo($model);
    }

    public function addresses(): HasMany
    {
        /** @var class-string<EthereumAddress> $model */
        $model = Ethereum::getModel(EthereumModel::Address);

        return $this->hasMany($model, 'wallet_id');
    }

    public function transactions(): HasManyThrough
    {
        /** @var class-string<EthereumTransaction> $transactionModel */
        $transactionModel = Ethereum::getModel(EthereumModel::Transaction);

        /** @var class-string<EthereumAddress> $addressModel */
        $addressModel = Ethereum::getModel(EthereumModel::Address);

        return $this->hasManyThrough(
            $transactionModel,
            $addressModel,
            'wallet_id',
            'address',
            'id',
            'address'
        );
    }

    public function getHasPasswordAttribute(): bool
    {
        return !!$this->password;
    }

    public function getHasMnemonicAttribute(): bool
    {
        return !!$this->mnemonic;
    }

    public function getHasSeedAttribute(): bool
    {
        return !!$this->seed;
    }
}
