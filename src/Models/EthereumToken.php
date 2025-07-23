<?php

namespace ItHealer\LaravelEthereum\Models;

use Illuminate\Database\Eloquent\Model;

class EthereumToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'address',
        'name',
        'symbol',
        'decimals',
    ];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
        ];
    }
}
