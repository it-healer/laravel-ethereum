<?php

namespace ItHealer\LaravelEthereum\Api\Node\DTO;

use Brick\Math\BigDecimal;
use ItHealer\LaravelEthereum\Api\BaseDTO;

class TransferDTO extends PreviewTransferDTO
{
    public function txid(): string
    {
        return $this->getOrFail('txid');
    }
}