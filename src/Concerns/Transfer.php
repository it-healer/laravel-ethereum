<?php

namespace ItHealer\LaravelEthereum\Concerns;

use Brick\Math\BigDecimal;
use ItHealer\LaravelEthereum\Api\Node\DTO\PreviewTransferDTO;
use ItHealer\LaravelEthereum\Api\Node\DTO\TransferDTO;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumToken;

trait Transfer
{
    public function previewTransfer(
        EthereumAddress $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?int $gasLimit = null
    ): PreviewTransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 2 + ($balanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->previewTransfer(
            from: $from->address,
            to: $to,
            amount: $amount,
            balanceBefore: $balanceBefore,
            gasLimit: $gasLimit
        );
    }

    public function transfer(
        EthereumAddress $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?int $gasLimit = null
    ): TransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 3 + ($balanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->transfer(
            from: $from->address,
            to: $to,
            privateKey: $from->private_key,
            amount: $amount,
            balanceBefore: $balanceBefore,
            gasLimit: $gasLimit,
        );
    }

    /**
     * Preview of an arbitrary transaction (value + call data) from a wallet address,
     * e.g. one requested by a dApp over WalletConnect.
     */
    public function previewCall(
        EthereumAddress $from,
        string $to,
        BigDecimal|int|string $valueWei,
        string $data = '',
        ?int $gasLimit = null,
        ?BigDecimal $balanceBefore = null,
    ): PreviewTransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 2 + ($balanceBefore === null ? 1 : 0));

        return $node->api()->previewCall(
            from: $from->address,
            to: $to,
            valueWei: BigDecimal::of($valueWei),
            data: $data,
            gasLimit: $gasLimit,
            balanceBefore: $balanceBefore,
        );
    }

    /**
     * Sign and broadcast an arbitrary transaction (see previewCall()).
     */
    public function sendCall(
        EthereumAddress $from,
        string $to,
        BigDecimal|int|string $valueWei,
        string $data = '',
        ?int $gasLimit = null,
        ?BigDecimal $balanceBefore = null,
    ): TransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 4 + ($balanceBefore === null ? 1 : 0));

        return $node->api()->sendCall(
            from: $from->address,
            to: $to,
            privateKey: $from->private_key,
            valueWei: BigDecimal::of($valueWei),
            data: $data,
            gasLimit: $gasLimit,
            balanceBefore: $balanceBefore,
        );
    }

    public function previewTokenTransfer(
        EthereumToken|string $contract,
        EthereumAddress $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): PreviewTransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 3 + ($balanceBefore === null ? 1 : 0) + ($tokenBalanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($contract instanceof EthereumToken) {
            $contract = $contract->address;
        }
        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->previewTokenTransfer(
            contract: $contract,
            from: $from->address,
            to: $to,
            amount: $amount,
            balanceBefore: $balanceBefore,
            tokenBalanceBefore: $tokenBalanceBefore,
            gasLimit: $gasLimit,
        );
    }

    public function transferToken(
        EthereumToken|string $contract,
        EthereumAddress $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): TransferDTO {
        $node = $from->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 4 + ($balanceBefore === null ? 1 : 0) + ($tokenBalanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($contract instanceof EthereumToken) {
            $contract = $contract->address;
        }
        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->transferToken(
            contract: $contract,
            from: $from->address,
            to: $to,
            privateKey: $from->private_key,
            amount: $amount,
            balanceBefore: $balanceBefore,
            tokenBalanceBefore: $tokenBalanceBefore,
            gasLimit: $gasLimit,
        );
    }

    public function previewTransferFromToken(
        EthereumToken|string $contract,
        EthereumAddress $collector,
        EthereumAddress|string $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): PreviewTransferDTO {
        $node = $collector->wallet->node ?? Ethereum::getNode();

        if( $from instanceof EthereumAddress ) {
            $from = $from->address;
        }

        $node->increment('requests', 3 + ($balanceBefore === null ? 1 : 0) + ($tokenBalanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($contract instanceof EthereumToken) {
            $contract = $contract->address;
        }
        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->previewTransferFromToken(
            contract: $contract,
            from: $from->address,
            to: $to,
            amount: $amount,
            balanceBefore: $balanceBefore,
            tokenBalanceBefore: $tokenBalanceBefore,
            gasLimit: $gasLimit,
        );
    }

    public function transferFromToken(
        EthereumToken|string $contract,
        EthereumAddress $collector,
        EthereumAddress|string $from,
        EthereumAddress|string $to,
        BigDecimal|float|int|string $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): TransferDTO {
        $node = $collector->wallet->node ?? Ethereum::getNode();
        $node->increment('requests', 4 + ($balanceBefore === null ? 1 : 0) + ($tokenBalanceBefore === null ? 1 : 0));
        $api = $node->api();

        if ($contract instanceof EthereumToken) {
            $contract = $contract->address;
        }
        if ($from instanceof EthereumAddress) {
            $from = $from->address;
        }
        if ($to instanceof EthereumAddress) {
            $to = $to->address;
        }
        $amount = BigDecimal::of($amount);

        return $api->transferFromToken(
            contract: $contract,
            from: $from,
            to: $to,
            privateKey: $collector->private_key,
            amount: $amount,
            balanceBefore: $balanceBefore,
            tokenBalanceBefore: $tokenBalanceBefore,
            gasLimit: $gasLimit,
        );
    }
}