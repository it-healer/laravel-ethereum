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

    /**
     * The account nonce allocated and signed for this transaction. Used to
     * reconcile stuck/replaced pending transfers against the confirmed chain nonce.
     */
    public function nonce(): ?int
    {
        $value = $this->get('nonce');

        return $value !== null ? (int) $value : null;
    }
}