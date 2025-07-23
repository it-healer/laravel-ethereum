<?php

namespace ItHealer\LaravelEthereum\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelEthereum\Api\Node\NodeApi;

class EthereumNode extends Model
{
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
        'worked',
        'available',
    ];

    protected function casts(): array
    {
        return [
            'sync_at' => 'datetime',
            'sync_data' => 'array',
            'requests_at' => 'date',
            'worked' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function api(): NodeApi
    {
        if( !$this->_api ) {
            $this->_api = new NodeApi($this->base_url, $this->proxy);
        }

        return $this->_api;
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
