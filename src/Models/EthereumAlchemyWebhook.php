<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single Alchemy Address Activity webhook for the Ethereum network.
 *
 * @property string $webhook_id
 * @property string $signing_key
 * @property int $addresses_count
 * @property bool $active
 */
class EthereumAlchemyWebhook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'signing_key',
        'addresses_count',
        'active',
    ];

    protected $hidden = [
        'signing_key',
    ];

    protected function casts(): array
    {
        return [
            'signing_key' => 'encrypted',
            'addresses_count' => 'integer',
            'active' => 'boolean',
        ];
    }
}
