<?php

namespace ItHealer\LaravelEthereum\Api\Explorer\DTO;

use Brick\Math\BigDecimal;
use ItHealer\LaravelEthereum\Api\BaseDTO;

class GasOracleDTO extends BaseDTO
{
    public function lastBlock(): int
    {
        return (int)$this->getOrFail('LastBlock');
    }

    public function safeGasPrice(): BigDecimal
    {
        $value = $this->getOrFail('SafeGasPrice');

        return BigDecimal::of($value);
    }

    public function proposeGasPrice(): BigDecimal
    {
        $value = $this->getOrFail('ProposeGasPrice');

        return BigDecimal::of($value);
    }

    public function fastGasPrice(): BigDecimal
    {
        $value = $this->getOrFail('FastGasPrice');

        return BigDecimal::of($value);
    }
}