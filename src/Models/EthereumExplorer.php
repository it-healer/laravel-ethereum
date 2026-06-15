<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelEthereum\Api\Explorer\AlchemyExplorerApi;
use ItHealer\LaravelEthereum\Api\Explorer\ExplorerApi;
use ItHealer\LaravelEthereum\Api\Explorer\ExplorerApiInterface;
use ItHealer\LaravelEthereum\Enums\ExplorerDriver;
use ItHealer\LaravelEthereum\Models\Concerns\TracksComputeUnits;

class EthereumExplorer extends Model
{
    use TracksComputeUnits;

    protected ?ExplorerApiInterface $_api = null;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'title',
        'driver',
        'base_url',
        'api_key',
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
            'driver' => ExplorerDriver::class,
            'sync_at' => 'datetime',
            'sync_data' => 'array',
            'requests_at' => 'date',
            'credits' => 'integer',
            'credits_at' => 'datetime',
            'worked' => 'boolean',
            'available' => 'boolean',
        ];
    }

    public function api(): ExplorerApiInterface
    {
        if (!$this->_api) {
            $this->_api = $this->driver === ExplorerDriver::Alchemy
                ? new AlchemyExplorerApi($this->base_url, $this->proxy)
                : new ExplorerApi($this->base_url, $this->api_key, $this->proxy);
        }

        return $this->_api;
    }
}
