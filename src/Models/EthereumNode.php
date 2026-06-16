<?php

namespace ItHealer\LaravelEthereum\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelEthereum\Api\Node\NodeApi;
use ItHealer\LaravelEthereum\Models\Concerns\TracksComputeUnits;
use ItHealer\LaravelEthereum\Services\Alchemy\ComputeUnits;
use ItHealer\LaravelEthereum\Services\Infura\InfuraCredits;

class EthereumNode extends Model
{
    use TracksComputeUnits;

    protected ?NodeApi $_api = null;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'title',
        'base_url',
        'proxy',
        'sync_at',
        'sync_data',
        'requests',
        'requests_at',
        'credits',
        'credits_at',
        'worked',
        'available',
    ];

    protected function casts(): array
    {
        return [
            'sync_at' => 'datetime',
            'sync_data' => 'array',
            'requests_at' => 'date',
            'credits' => 'integer',
            'credits_at' => 'datetime',
            'worked' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function api(): NodeApi
    {
        if( !$this->_api ) {
            $this->_api = new NodeApi(
                $this->base_url,
                $this->proxy,
                fn (string $method) => $this->recordCredits($this->creditCost($method)),
            );
        }

        return $this->_api;
    }

    /**
     * Credit cost of an RPC method for this node's provider (Infura credits, Alchemy CU, or 0
     * for unmetered nodes such as self-hosted).
     */
    public function creditCost(string $method): int
    {
        return match ($this->creditProvider()) {
            'infura' => InfuraCredits::cost($method),
            'alchemy' => ComputeUnits::cost($method),
            default => 0,
        };
    }

    public function getLatestBlockNumber(): int
    {
        $result = $this->api()->getLatestBlockNumber();

        $this->increment('requests');

        return $result;
    }

    public function getBalance(string|EthereumAddress $address): BigDecimal
    {
        if( $address instanceof EthereumAddress ) {
            $address = $address->address;
        }

        $result = $this->api()->getBalance($address);

        $this->increment('requests');

        return $result;
    }

    public function getBalanceOfToken(string|EthereumAddress $address, string|EthereumToken $contract): BigDecimal
    {
        if( $address instanceof EthereumAddress ) {
            $address = $address->address;
        }
        if( $contract instanceof EthereumToken ) {
            $contract = $contract->address;
        }

        $result = $this->api()->getBalanceOfToken($address, $contract);

        $this->increment('requests');

        return $result;
    }
}
