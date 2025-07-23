<?php

namespace ItHealer\LaravelEthereum\Concerns;

use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;
use ItHealer\LaravelEthereum\Models\EthereumWallet;

trait Wallet
{
    public function importWallet(
        string $name,
        string|array $mnemonic,
        ?string $passphrase = null,
        ?string $password = null,
        ?bool $savePassword = true,
        ?EthereumNode $node = null,
        ?EthereumExplorer $explorer = null,
    ): EthereumWallet {
        if (is_array($mnemonic)) {
            $mnemonic = implode(" ", $mnemonic);
        }

        $seed = Ethereum::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<EthereumWallet> $walletModel */
        $walletModel = Ethereum::getModel(EthereumModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'explorer_id' => $explorer?->id,
            'name' => $name,
        ]);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = $mnemonic;
        $wallet->seed = $seed;

        return $wallet;
    }

    public function generateWallet(
        string $name,
        ?int $mnemonicSize = 18,
        ?string $passphrase = null,
        ?string $password = null,
        ?bool $savePassword = true,
        ?EthereumNode $node = null,
        ?EthereumExplorer $explorer = null,
    ): EthereumWallet {
        $mnemonic = Ethereum::mnemonicGenerate($mnemonicSize ?? 18);
        $seed = Ethereum::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<EthereumWallet> $walletModel */
        $walletModel = Ethereum::getModel(EthereumModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'explorer_id' => $explorer?->id,
            'name' => $name,
        ]);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = implode(" ", $mnemonic);
        $wallet->seed = $seed;

        return $wallet;
    }

    public function newWallet(
        string $name,
        ?string $password = null,
        ?bool $savePassword = true,
        ?EthereumNode $node = null,
        ?EthereumExplorer $explorer = null,
    ): EthereumWallet {
        /** @var class-string<EthereumWallet> $walletModel */
        $walletModel = Ethereum::getModel(EthereumModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'explorer_id' => $explorer?->id,
            'name' => $name,
        ]);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }

        return $wallet;
    }

    public function createWallet(
        string $name,
        ?string $password = null,
        ?bool $savePassword = true,
        string|array|int|null $mnemonic = null,
        ?string $passphrase = null,
        ?EthereumNode $node = null,
        ?EthereumExplorer $explorer = null,
    ): EthereumWallet {
        if (is_string($mnemonic)) {
            $mnemonic = explode(' ', $mnemonic);
        } elseif (is_null($mnemonic) || is_int($mnemonic)) {
            $mnemonic = Ethereum::mnemonicGenerate($mnemonic ?? 18);
        }

        $seed = Ethereum::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<EthereumWallet> $walletModel */
        $walletModel = Ethereum::getModel(EthereumModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'explorer_id' => $explorer?->id,
            'name' => $name,
        ]);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = implode(" ", $mnemonic);
        $wallet->seed = $seed;
        $wallet->save();

        Ethereum::createAddress($wallet, 'Primary Address', 0);

        return $wallet;
    }
}
