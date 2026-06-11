<?php

namespace ItHealer\LaravelEthereum;

use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelEthereum\Concerns\Address;
use ItHealer\LaravelEthereum\Concerns\Explorer;
use ItHealer\LaravelEthereum\Concerns\Mnemonic;
use ItHealer\LaravelEthereum\Concerns\Node;
use ItHealer\LaravelEthereum\Concerns\Token;
use ItHealer\LaravelEthereum\Concerns\Transfer;
use ItHealer\LaravelEthereum\Concerns\Wallet;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Models\EthereumExplorer;
use ItHealer\LaravelEthereum\Models\EthereumNode;

class Ethereum
{
    use Node, Explorer, Token, Mnemonic, Address, Wallet, Transfer;

    /** Standard BIP-44 path used by MetaMask, Trust Wallet and most software wallets. */
    public const PATH_BIP44 = "m/44'/60'/0'/0/{index}";

    /** Path used by Ledger Live. */
    public const PATH_LEDGER_LIVE = "m/44'/60'/{index}'/0/0";

    /** Legacy path used by older Ledger firmware / MyEtherWallet. */
    public const PATH_LEDGER_LEGACY = "m/44'/60'/0'/{index}";

    /**
     * @param EthereumModel $model
     * @return class-string<Model>
     */
    public function getModel(EthereumModel $model): string
    {
        return config('ethereum.models.'.$model->value);
    }

    public function getNode(): EthereumNode
    {
        return $this->getModel(EthereumModel::Node)::query()
            ->where('worked', '=', true)
            ->where('available', '=', true)
            ->orderBy('requests')
            ->firstOrFail();
    }

    public function getExplorer(): EthereumExplorer
    {
        return $this->getModel(EthereumModel::Explorer)::query()
            ->where('worked', '=', true)
            ->where('available', '=', true)
            ->orderBy('requests')
            ->firstOrFail();
    }
}
