<?php

namespace ItHealer\LaravelEthereum\Concerns;

use BIP\BIP44;
use Brick\Math\BigDecimal;
use kornrunner\Keccak;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumToken;
use ItHealer\LaravelEthereum\Models\EthereumWallet;

trait Address
{
    public function createAddress(
        EthereumWallet $wallet,
        ?string $title = null,
        ?int $index = null,
        ?string $seed = null,
        ?string $derivationPath = null
    ): EthereumAddress {
        $address = $this->newAddress($wallet, $title, $index, $seed, $derivationPath);
        $address->save();

        return $address;
    }

    public function newAddress(
        EthereumWallet $wallet,
        ?string $title = null,
        ?int $index = null,
        ?string $seed = null,
        ?string $derivationPath = null
    ): EthereumAddress {
        if ($index === null) {
            $index = $wallet->addresses()->max('index');
            $index = $index === null ? 0 : ($index + 1);
        }

        if (!$seed) {
            $seed = $wallet->seed;
        }

        if (!$seed) {
            throw new \Exception('Argument Seed is required.');
        }

        $derivationPath ??= $wallet->derivation_path
            ?? config('ethereum.wallet.default_derivation_path', \ItHealer\LaravelEthereum\Ethereum::PATH_BIP44);

        $hdKey = BIP44::fromMasterSeed($seed)
            ->derive($this->resolveDerivationPath($derivationPath, $index));
        $privateKey = (string)$hdKey->privateKey;

        $addressString = '0x'.(new \kornrunner\Ethereum\Address($privateKey))->get();
        $addressString = Ethereum::toChecksumAddress($addressString);

        /** @var class-string<EthereumAddress> $addressModel */
        $addressModel = Ethereum::getModel(EthereumModel::Address);

        $address = new $addressModel([
            'address' => $addressString,
            'title' => $title,
            'index' => $index,
        ]);
        $address->wallet()->associate($wallet);
        $address->private_key = $privateKey;

        return $address;
    }

    /**
     * Resolves a derivation path template (e.g. "m/44'/60'/0'/0/{index}")
     * into a concrete path for the given address index.
     */
    public function resolveDerivationPath(string $pathTemplate, int $index): string
    {
        $path = str_replace('{index}', (string)$index, $pathTemplate);

        if (!$this->validateDerivationPath($path)) {
            throw new \InvalidArgumentException("Invalid derivation path: {$path}");
        }

        if (!str_contains($pathTemplate, '{index}') && $index > 0) {
            throw new \InvalidArgumentException(
                "Derivation path template \"{$pathTemplate}\" has no {index} placeholder, only index 0 is allowed."
            );
        }

        return $path;
    }

    public function validateDerivationPath(string $path): bool
    {
        return (bool)preg_match("/^m(\/\d+'?)+$/", str_replace('{index}', '0', $path));
    }

    public function importAddress(EthereumWallet $wallet, string $address)
    {
        return $wallet->addresses()->create([
            'address' => $address,
            'watch_only' => true,
        ]);
    }

    public function validateAddress(string $address): bool
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }

        if (strtolower($address) === $address || strtoupper($address) === $address) {
            return true;
        }

        $addressNoPrefix = substr($address, 2);
        $hash = Keccak::hash(strtolower($addressNoPrefix), 256);

        for ($i = 0; $i < 40; $i++) {
            $char = $addressNoPrefix[$i];
            $expectedCase = hexdec($hash[$i]) > 7 ? strtoupper($char) : strtolower($char);
            if ($char !== $expectedCase) {
                return false;
            }
        }

        return true;
    }

    public function toChecksumAddress(string $address): string
    {
        $address = strtolower(str_replace('0x', '', $address));
        $hash = Keccak::hash($address, 256);

        $checksum = '0x';

        for ($i = 0; $i < strlen($address); $i++) {
            $char = $address[$i];
            $checksum .= (hexdec($hash[$i]) >= 8) ? strtoupper($char) : $char;
        }

        return $checksum;
    }

    public function privateKeyToAddress(string $privateKey): string
    {
        $hex = '0x'.(new \kornrunner\Ethereum\Address($privateKey))->get();
        return $this->toChecksumAddress($hex);
    }

    public function getBalance(string|EthereumAddress $address): BigDecimal
    {
        $node = $address instanceof EthereumAddress ? $address->wallet->node : Ethereum::getNode();

        return $node->getBalance($address);
    }

    public function getBalanceOfToken(string|EthereumAddress $address, string|EthereumToken $contract): BigDecimal
    {
        $node = $address instanceof EthereumAddress ? $address->wallet->node : Ethereum::getNode();

        return $node->getBalanceOfToken($address, $contract);
    }
}
