<?php

namespace ItHealer\LaravelEthereum\Api\Explorer;

use Brick\Math\BigInteger;
use Closure;
use Illuminate\Support\Facades\Http;
use ItHealer\LaravelEthereum\Api\DTOPaginator;
use ItHealer\LaravelEthereum\Api\Explorer\DTO\TokenTransactionDTO;
use ItHealer\LaravelEthereum\Api\Explorer\DTO\TransactionDTO;
use ItHealer\LaravelEthereum\Services\Alchemy\ComputeUnits;
use ItHealer\LaravelEthereum\Support\ProxyFormatter;

/**
 * Alchemy Transfers API (alchemy_getAssetTransfers) as an explorer driver. Returns the same
 * TransactionDTO/TokenTransactionDTO (Etherscan-shaped) the sync expects, so it is a drop-in
 * alternative to the Etherscan driver. Incoming and outgoing transfers need separate requests
 * (toAddress / fromAddress); pagination is cursor based (pageKey). Confirmations are computed
 * from the latest block (Alchemy does not return them).
 */
class AlchemyExplorerApi implements ExplorerApiInterface
{
    protected ?string $proxy;
    protected ?int $latestBlock = null;

    public function __construct(protected string $baseURL, ?string $proxy = null)
    {
        $this->proxy = ProxyFormatter::format($proxy);
    }

    public function creditsPerRequest(): int
    {
        return ComputeUnits::cost('alchemy_getAssetTransfers');
    }

    public function healthCheck(): bool
    {
        try {
            $this->rpc('eth_blockNumber');

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    public function rpc(string $method, array $params = []): mixed
    {
        $response = Http::asJson()
            ->acceptJson()
            ->withOptions([
                'base_uri' => $this->baseURL,
                'timeout' => 60,
                'proxy' => $this->proxy,
            ])
            ->post('', [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

        $result = $response->json();

        if (isset($result['error'])) {
            throw new \Exception($result['error']['message']);
        }

        if (count($result ?? []) === 0 || !array_key_exists('result', $result)) {
            throw new \Exception($response->body());
        }

        return $result['result'];
    }

    public function getTransactionsPaginator(string $address, int $startBlock = 0, int $perPage = 10, ?Closure $callback = null): DTOPaginator
    {
        $items = null;

        return new DTOPaginator(function (int $page) use (&$items, $address, $startBlock, $perPage, $callback): array {
            $items ??= $this->fetch($address, ['external'], null, $startBlock, false, $callback);

            return array_slice($items, ($page - 1) * $perPage, $perPage);
        }, $perPage);
    }

    public function getTokenTransactionsPaginator(string $address, ?string $contract = null, int $startBlock = 0, int $perPage = 10, ?Closure $callback = null): DTOPaginator
    {
        $items = null;

        return new DTOPaginator(function (int $page) use (&$items, $address, $contract, $startBlock, $perPage, $callback): array {
            $items ??= $this->fetch($address, ['erc20'], $contract, $startBlock, true, $callback);

            return array_slice($items, ($page - 1) * $perPage, $perPage);
        }, $perPage);
    }

    /**
     * @return array<TransactionDTO|TokenTransactionDTO>
     */
    protected function fetch(string $address, array $categories, ?string $contract, int $startBlock, bool $isToken, ?Closure $callback): array
    {
        // Deposit detection only needs incoming transfers; disabling outgoing halves the requests.
        $directions = config('ethereum.sync.track_outgoing', true)
            ? ['toAddress', 'fromAddress']
            : ['toAddress'];

        $latest = $this->latestBlock();
        $items = [];

        foreach ($directions as $direction) {
            $pageKey = null;

            do {
                if ($callback) {
                    $callback();
                }

                $params = [
                    'fromBlock' => '0x'.dechex($startBlock),
                    'toBlock' => 'latest',
                    'category' => $categories,
                    'withMetadata' => true,
                    'excludeZeroValue' => false,
                    'order' => 'asc',
                    'maxCount' => '0x3e8',
                    $direction => $address,
                ];

                if ($contract) {
                    $params['contractAddresses'] = [$contract];
                }

                if ($pageKey) {
                    $params['pageKey'] = $pageKey;
                }

                $result = $this->rpc('alchemy_getAssetTransfers', [$params]);

                foreach ($result['transfers'] ?? [] as $item) {
                    $items[] = $isToken ? $this->mapToken($item, $latest) : $this->mapNative($item, $latest);
                }

                $pageKey = $result['pageKey'] ?? null;
            } while ($pageKey !== null);
        }

        return $items;
    }

    protected function latestBlock(): int
    {
        return $this->latestBlock ??= hexdec($this->rpc('eth_blockNumber'));
    }

    /**
     * Raw amount (smallest unit) as an integer string, from rawContract.value (hex).
     */
    protected function rawValue(array $item): string
    {
        $hex = $item['rawContract']['value'] ?? null;

        if (!$hex) {
            return '0';
        }

        $hex = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;

        return $hex === '' ? '0' : (string) BigInteger::fromBase($hex, 16);
    }

    protected function mapNative(array $item, int $latest): TransactionDTO
    {
        $block = hexdec($item['blockNum']);

        return TransactionDTO::make([
            'hash' => $item['hash'],
            'blockNumber' => $block,
            'timeStamp' => strtotime($item['metadata']['blockTimestamp'] ?? 'now'),
            'from' => $item['from'],
            'to' => $item['to'] ?? '',
            'value' => $this->rawValue($item),
            'contractAddress' => '',
            'confirmations' => max(0, $latest - $block),
            'isError' => 0,
        ]);
    }

    protected function mapToken(array $item, int $latest): TokenTransactionDTO
    {
        $block = hexdec($item['blockNum']);
        $decimalHex = $item['rawContract']['decimal'] ?? null;
        $decimals = $decimalHex ? hexdec($decimalHex) : 18;

        return TokenTransactionDTO::make([
            'hash' => $item['hash'],
            'blockNumber' => $block,
            'timeStamp' => strtotime($item['metadata']['blockTimestamp'] ?? 'now'),
            'from' => $item['from'],
            'to' => $item['to'] ?? '',
            'contractAddress' => $item['rawContract']['address'] ?? '',
            'tokenDecimal' => $decimals,
            'value' => $this->rawValue($item),
            'tokenName' => '',
            'tokenSymbol' => $item['asset'] ?? '',
            'confirmations' => max(0, $latest - $block),
        ]);
    }
}
