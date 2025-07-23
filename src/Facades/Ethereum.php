<?php

namespace ItHealer\LaravelEthereum\Facades;

use Illuminate\Support\Facades\Facade;

class Ethereum extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ItHealer\LaravelEthereum\Ethereum::class;
    }
}
