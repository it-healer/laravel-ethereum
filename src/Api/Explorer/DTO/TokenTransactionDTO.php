<?php

namespace ItHealer\LaravelEthereum\Api\Explorer\DTO;

use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use ItHealer\LaravelEthereum\Api\BaseDTO;

class TokenTransactionDTO extends BaseDTO
{
    public function hash(): string
    {
        return (string)$this->getOrFail('hash');
    }

    public function blockNumber(): int
    {
        return (int)$this->getOrFail('blockNumber');
    }

    public function time(): Carbon
    {
        $value = $this->getOrFail('timeStamp');

        return Date::createFromTimestampUTC($value);
    }

    public function from(): string
    {
        return Str::lower($this->getOrFail('from'));
    }

    public function contractAddress(): string
    {
        return Str::lower($this->getOrFail('contractAddress'));
    }

    public function to(): string
    {
        return Str::lower($this->getOrFail('to'));
    }

    public function tokenDecimal(): int
    {
        return (int)$this->getOrFail('tokenDecimal');
    }

    public function amount(): BigDecimal
    {
        $decimal = $this->tokenDecimal();
        $value = $this->getOrFail('value');

        return BigDecimal::ofUnscaledValue($value, $decimal);
    }

    public function tokenName(): string
    {
        return (string)$this->getOrFail('tokenName');
    }

    public function tokenSymbol(): string
    {
        return (string)$this->getOrFail('tokenSymbol');
    }

    public function gas(): int
    {
        return (int)$this->getOrFail('gas');
    }

    public function gasUsed(): int
    {
        return (int)$this->getOrFail('gasUsed');
    }

    public function gasPrice(): BigDecimal
    {
        $value = $this->getOrFail('gasPrice');

        return BigDecimal::ofUnscaledValue($value, 18);
    }

    public function fee(): BigDecimal
    {
        $gasUsed = $this->gasUsed();
        $gasPrice = $this->getOrFail('gasPrice');

        return $gasPrice->multipliedBy($gasUsed);
    }

    public function confirmations(): int
    {
        return (int)$this->getOrFail('confirmations');
    }

    public function input(): ?string
    {
        return $this->getOrFail('input');
    }
}
