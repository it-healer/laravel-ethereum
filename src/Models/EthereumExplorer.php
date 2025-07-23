<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelEthereum\Api\Explorer\ExplorerApi;

class EthereumExplorer extends Model
{
    protected ?ExplorerApi $_api = null;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'title',
        'base_url',
        'api_key',
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

    public function api(): ExplorerApi
    {
        if (!$this->_api) {
            $this->_api = new ExplorerApi(
                $this->base_url,
                $this->api_key,
                $this->proxy,
            );
        }

        return $this->_api;
    }
}
