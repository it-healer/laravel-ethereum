<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single Alchemy Address Activity webhook for the Ethereum network.
 *
 * @property string $webhook_id
 * @property string $signing_key
 * @property string|null $auth_token
 * @property string|null $account_ref
 * @property int $addresses_count
 * @property bool $active
 */
class EthereumAlchemyWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'signing_key',
        'auth_token',
        'account_ref',
        'addresses_count',
        'active',
    ];

    protected $hidden = [
        'signing_key',
        'auth_token',
    ];

    protected function casts(): array
    {
        return [
            'signing_key' => 'encrypted',
            'auth_token' => 'encrypted',
            'addresses_count' => 'integer',
            'active' => 'boolean',
        ];
    }
}
