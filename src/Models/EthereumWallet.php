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
        'derivation_path',
        'sync_at',
        'balance',
        'tokens',
    ];

    protected $appends = [
        'tokens_balances',
        'available_balance',
        'available_tokens_balances',
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

    /**
     * Aggregated wallet balance minus broadcast-but-unconfirmed outgoing transfers
     * (amount + fees) across all wallet addresses.
     */
    protected function availableBalance(): Attribute
    {
        return new Attribute(
            get: function (): string {
                $addresses = $this->addresses()->pluck('address')->all();
                $pending = \ItHealer\LaravelEthereum\Services\PendingBalance::forAddresses($addresses);

                $native = \Brick\Math\BigDecimal::zero();
                $fee = \Brick\Math\BigDecimal::zero();
                foreach ($pending as $row) {
                    $native = $native->plus($row['native']);
                    $fee = $fee->plus($row['fee']);
                }

                $available = \Brick\Math\BigDecimal::of($this->balance ?? 0)->minus($native)->minus($fee);

                return (string) ($available->isNegative() ? \Brick\Math\BigDecimal::zero() : $available);
            }
        );
    }

    /**
     * Aggregated wallet token balances reduced by pending outgoing token transfers.
     */
    protected function availableTokensBalances(): Attribute
    {
        /** @var class-string<EthereumToken> $model */
        $tokenModel = Ethereum::getModel(EthereumModel::Token);

        return new Attribute(
            get: function () use ($tokenModel) {
                $addresses = $this->addresses()->pluck('address')->all();
                $pending = \ItHealer\LaravelEthereum\Services\PendingBalance::forAddresses($addresses);

                $tokenPending = [];
                foreach ($pending as $row) {
                    foreach ($row['tokens'] as $contract => $amount) {
                        $tokenPending[$contract] = ($tokenPending[$contract] ?? \Brick\Math\BigDecimal::zero())->plus($amount);
                    }
                }

                return $tokenModel::get()->map(function (Model $token) use ($tokenPending) {
                    $confirmed = $this->tokens[$token->address] ?? null;
                    $available = $confirmed !== null
                        ? \Brick\Math\BigDecimal::of($confirmed)->minus($tokenPending[$token->address] ?? \Brick\Math\BigDecimal::zero())
                        : null;

                    if ($available !== null && $available->isNegative()) {
                        $available = \Brick\Math\BigDecimal::zero();
                    }

                    return [
                        ...$token->only(['address', 'name', 'symbol', 'decimals']),
                        'balance' => $available !== null ? (string) $available : null,
                    ];
                })->keyBy('address');
            }
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
