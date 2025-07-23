<?php

namespace ItHealer\LaravelEthereum\Api\Node\DTO;

use Brick\Math\BigDecimal;
use ItHealer\LaravelEthereum\Api\BaseDTO;

class PreviewTransferDTO extends BaseDTO
{
    public function isToken(): bool
    {
        return !!$this->get('contract');
    }

    public function contract(): string
    {
        return $this->getOrFail('contract');
    }

    public function from(): string
    {
        return $this->getOrFail('from');
    }

    public function to(): string
    {
        return $this->getOrFail('to');
    }

    public function amount(): BigDecimal
    {
        $value = $this->getOrFail('amount');

        return BigDecimal::of($value);
    }

    public function data(): string
    {
        return $this->getOrFail('data');
    }

    public function gasPrice(): BigDecimal
    {
        $value = $this->getOrFail('gas_price');

        return BigDecimal::of($value);
    }

    public function gasLimit(): BigDecimal
    {
        $value = $this->getOrFail('gas_limit');

        return BigDecimal::of($value);
    }

    public function fee(): BigDecimal
    {
        $value = $this->getOrFail('fee');

        return BigDecimal::of($value);
    }

    public function balanceBefore(): BigDecimal
    {
        $value = $this->getOrFail('balance_before');

        return BigDecimal::of($value);
    }

    public function balanceAfter(): BigDecimal
    {
        $value = $this->getOrFail('balance_after');

        return BigDecimal::of($value);
    }

    public function tokenBalanceBefore(): BigDecimal
    {
        $value = $this->getOrFail('token_balance_before');

        return BigDecimal::of($value);
    }

    public function tokenBalanceAfter(): BigDecimal
    {
        $value = $this->getOrFail('token_balance_after');

        return BigDecimal::of($value);
    }

    public function error(): ?string
    {
        return $this->getOrFail('error');
    }

    public function hasError(): bool
    {
        return $this->get('error') !== null;
    }
}