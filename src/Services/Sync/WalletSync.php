<?php

namespace ItHealer\LaravelEthereum\Services\Sync;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Models\EthereumWallet;
use ItHealer\LaravelEthereum\Services\BaseSync;

class WalletSync extends BaseSync
{
    protected EthereumWallet $wallet;

    public function __construct(EthereumWallet $wallet)
    {
        $this->wallet = $wallet;
    }

    public function run(): void
    {
        parent::run();

        $this
            ->syncAddresses()
            ->calculateBalances();
    }

    protected function syncAddresses(): static
    {
        $addresses = $this->wallet
            ->addresses()
            ->where('available', true)
            ->get();
        foreach ($addresses as $address) {
            $this->log('- Started sync address '.$address->address.'...');

            $service = App::make(AddressSync::class, [
                'address' => $address
            ]);

            $service->setLogger($this->logger);

            $service->run();

            $this->log('- Finished sync address '.$address->address, 'success');
        }

        return $this;
    }

    protected function calculateBalances(): self
    {
        $balance = BigDecimal::of('0');
        $tokens = [];

        /** @var EthereumAddress $address */
        $addresses = $this->wallet
            ->addresses()
            ->where('available', true)
            ->get();
        foreach ($addresses as $address) {
            $balance = $balance->plus(($address->balance ?: 0));

            foreach ($address->tokens as $k => $v) {
                $current = BigDecimal::of($tokens[$k] ?? 0);
                $tokens[$k] = $current->plus($v)->__toString();
            }
        }

        $this->wallet->update([
            'sync_at' => Date::now(),
            'balance' => $balance,
            'tokens' => $tokens,
        ]);

        return $this;
    }
}