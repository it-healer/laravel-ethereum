<?php

namespace ItHealer\LaravelEthereum\Api\Node;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use kornrunner\Ethereum\Transaction;
use ItHealer\LaravelEthereum\Api\Node\DTO\PreviewTransferDTO;
use ItHealer\LaravelEthereum\Api\Node\DTO\TransferDTO;

class NodeApi
{
    protected string $baseURL;
    protected ?string $proxy;
    protected array $tokenDecimals = [];
    protected ?Closure $onUsage;

    /**
     * @param  Closure(string $method): void|null  $onUsage  called once per RPC request with the method name
     */
    public function __construct(string $baseURL, ?string $proxy = null, ?Closure $onUsage = null)
    {
        $this->baseURL = $baseURL;
        $this->proxy = $this->formatProxy($proxy);
        $this->onUsage = $onUsage;
    }

    /**
     * Отправка транзакции с безопасным выделением nonce.
     *
     * Нода может не отражать только что отправленные транзакции в
     * eth_getTransactionCount(pending) (лаг мемпула, балансировщик нод),
     * из-за чего последовательные транзакции получают одинаковый nonce и
     * вытесняют друг друга из мемпула. Поэтому:
     *  - отправки с одного адреса сериализуются через Cache::lock;
     *  - используется max(nonce ноды, локально зарезервированный nonce);
     *  - после успешной отправки следующий nonce запоминается в кэше
     *    (TTL 10 минут — страховка на случай вытеснения транзакции).
     *
     * @param  callable(int $nonce): string  $buildRawTransaction  возвращает подписанную raw-транзакцию ('0x...')
     */
    protected function sendWithSafeNonce(string $from, callable $buildRawTransaction, ?int &$usedNonce = null): string
    {
        $from = Str::lower($from);
        $lock = Cache::lock('ethereum:transfer-lock:'.$from, 60);

        $nonce = null;

        $txid = $lock->block(60, function () use ($from, $buildRawTransaction, &$nonce): string {
            $chainNonce = hexdec(substr($this->rpc('eth_getTransactionCount', [$from, 'pending']), 2));
            $localNonce = (int) Cache::get('ethereum:next-nonce:'.$from, 0);
            $nonce = max($chainNonce, $localNonce);

            $raw = $buildRawTransaction($nonce);
            $txid = $this->rpc('eth_sendRawTransaction', [$raw]);

            Cache::put('ethereum:next-nonce:'.$from, $nonce + 1, now()->addMinutes(10));

            return $txid;
        });

        $usedNonce = $nonce;

        return $txid;
    }

    public function rpc(string $method, array $params = []): mixed
    {
        $client = Http::asJson()
            ->acceptJson()
            ->withOptions([
                'base_uri' => $this->baseURL,
                'timeout' => 60,
                'proxy' => $this->proxy,
            ]);

        $response = $client->post('', [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1
        ]);

        if ($this->onUsage) {
            ($this->onUsage)($method);
        }

        $result = $response->json();

        if (isset($result['error'])) {
            throw new \Exception($result['error']['message']);
        }

        if (count($result ?? []) === 0 || !array_key_exists('result', $result)) {
            throw new \Exception($response->body());
        }

        return $result['result'];
    }

    protected function formatProxy(?string $proxy): ?string
    {
        if (!$proxy) {
            return null;
        }

        if (preg_match('/^(socks4|socks5|https?|http):\/\/(([^:]+):([^@]+)@)?([^:\/]+)(:\d+)?$/', $proxy, $matches)) {
            $protocol = $matches[1];
            $username = $matches[3] ?? null;
            $password = $matches[4] ?? null;
            $host = $matches[5];
            $port = $matches[6] ?? '';

            if (in_array($protocol, ['socks4', 'socks5'])) {
                if ($username && $password) {
                    return "{$protocol}://{$username}:{$password}@{$host}{$port}";
                }
                return "{$protocol}://{$host}{$port}";
            }

            if ($username && $password) {
                return "{$protocol}://{$username}:{$password}@{$host}{$port}";
            }

            return "{$protocol}://{$host}{$port}";
        }

        throw new \InvalidArgumentException('Invalid proxy format. Supported formats: socks4|socks5|http|https.');
    }

    public function getBalance(string $address): BigDecimal
    {
        $balanceHex = $this->rpc('eth_getBalance', [$address, 'latest']);

        return BigDecimal::ofUnscaledValue(hexdec($balanceHex), 18);
    }

    public function getTokenName(string $contract): ?string
    {
        $hex = $this->rpc('eth_call', [
            [
                'to' => $contract,
                'data' => '0x06fdde03'
            ],
            'latest'
        ]);

        return trim(hex2bin(substr($hex, 130)), "\0");
    }

    public function getTokenSymbol(string $contract): ?string
    {
        $hex = $this->rpc('eth_call', [
            [
                'to' => $contract,
                'data' => '0x95d89b41'
            ],
            'latest'
        ]);

        return trim(hex2bin(substr($hex, 130)), "\0");
    }

    public function getTokenDecimals(string $contract): int
    {
        $hex = $this->rpc('eth_call', [
            [
                'to' => $contract,
                'data' => '0x313ce567'
            ],
            'latest'
        ]);

        return (int)hexdec($hex);
    }

    public function getBalanceOfToken(string $address, string $contract): BigDecimal
    {
        $decimals = $this->tokenDecimals[$contract] ??= $this->getTokenDecimals($contract);

        $data = '0x70a08231000000000000000000000000'.substr($address, 2);
        $balanceHex = $this->rpc('eth_call', [
            [
                'to' => $contract,
                'data' => $data
            ],
            'latest'
        ]);

        return BigDecimal::ofUnscaledValue(hexdec($balanceHex), $decimals);
    }

    public function getLatestBlockNumber(): int
    {
        $hex = $this->rpc('eth_blockNumber');

        return (int)hexdec($hex);
    }

    /**
     * Number of confirmed (mined) transactions sent from the address. A pending
     * transfer whose nonce is below this has been mined or replaced.
     */
    public function getConfirmedNonce(string $address): int
    {
        return (int)hexdec(substr($this->rpc('eth_getTransactionCount', [Str::lower($address), 'latest']), 2));
    }

    /**
     * Block number of a mined transaction, or null when it is not (yet) mined.
     */
    public function getTransactionBlockNumber(string $txid): ?int
    {
        $receipt = $this->rpc('eth_getTransactionReceipt', [$txid]);

        if (! is_array($receipt) || ! isset($receipt['blockNumber'])) {
            return null;
        }

        return (int)hexdec($receipt['blockNumber']);
    }

    /**
     * Block number and execution outcome of a mined transaction, or null when it is not
     * (yet) mined. `failed` is true when the receipt status is 0x0 (the transaction was
     * included in a block but reverted / ran out of gas).
     *
     * @return array{blockNumber: int, failed: bool}|null
     */
    public function getTransactionReceipt(string $txid): ?array
    {
        $receipt = $this->rpc('eth_getTransactionReceipt', [$txid]);

        if (! is_array($receipt) || ! isset($receipt['blockNumber'])) {
            return null;
        }

        return [
            'blockNumber' => (int)hexdec($receipt['blockNumber']),
            'failed' => isset($receipt['status']) && hexdec($receipt['status']) === 0,
        ];
    }

    /**
     * Whether the node still knows the transaction. Returns the block number when mined,
     * a null block number while it is still in the mempool, or null when the node has
     * never seen it (dropped/evicted from the mempool and never mined).
     *
     * @return array{blockNumber: ?int}|null
     */
    public function getTransactionByHash(string $txid): ?array
    {
        $tx = $this->rpc('eth_getTransactionByHash', [$txid]);

        if (! is_array($tx)) {
            return null;
        }

        $blockNumber = isset($tx['blockNumber']) && $tx['blockNumber'] !== null
            ? (int)hexdec($tx['blockNumber'])
            : null;

        return ['blockNumber' => $blockNumber];
    }

    public static function hexToBigDecimal(string $hex): BigDecimal
    {
        $value = ltrim($hex, '0x');
        $value = BigInteger::fromBase($value, 16);
        return BigDecimal::ofUnscaledValue($value);
    }

    public static function bigDecimalToHex(BigDecimal $bigDecimal): string
    {
        $hex = $bigDecimal->toBigInteger()->toBase(16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return $hex;
    }

    /**
     * Gas price (wei) for legacy transactions, with headroom so the transaction gets into the
     * next blocks. A bare `eth_gasPrice` is the current base fee plus an almost zero tip: once
     * the base fee rises (up to 12.5% per block) such a transaction is below it and waits in the
     * mempool for minutes. Here: the next block's base fee times `ethereum.gas.base_fee_multiplier`
     * (1.25 — two blocks of maximum growth) plus the median tip of recent blocks (at least
     * `ethereum.gas.min_priority_fee_gwei`), never below the node's own price. A legacy
     * transaction pays its full gas price, so the headroom is the price of fast inclusion.
     * If the node does not support `eth_feeHistory`, its `eth_gasPrice` is used as is.
     */
    public function gasPrice(): BigDecimal
    {
        $nodePrice = static::hexToBigDecimal($this->rpc('eth_gasPrice'));

        try {
            $history = $this->rpc('eth_feeHistory', ['0xa', 'latest', [50]]);
        } catch (\Throwable) {
            return $nodePrice;
        }

        $baseFees = (array) ($history['baseFeePerGas'] ?? []);

        if ($baseFees === []) {
            return $nodePrice;
        }

        // The last element is the base fee of the next block
        $nextBaseFee = static::hexToBigInteger((string) end($baseFees));

        $tips = array_map(fn ($reward) => static::hexToBigInteger((string) ($reward[0] ?? '0x0')), (array) ($history['reward'] ?? []));
        usort($tips, fn (BigInteger $a, BigInteger $b) => $a->compareTo($b));
        $medianTip = $tips === [] ? BigInteger::zero() : $tips[intdiv(count($tips), 2)];

        $minTip = BigDecimal::of((string) config('ethereum.gas.min_priority_fee_gwei', '0.01'))
            ->multipliedBy(1_000_000_000)
            ->toBigInteger();
        $tip = $medianTip->isGreaterThan($minTip) ? $medianTip : $minTip;

        $price = BigDecimal::of($nextBaseFee)
            ->multipliedBy((string) config('ethereum.gas.base_fee_multiplier', '1.25'))
            ->plus($tip)
            ->toScale(0, RoundingMode::Up);

        return $price->isGreaterThan($nodePrice) ? $price : $nodePrice;
    }

    /**
     * Hex quantity (0x…) as an integer; `0x0` and `0x` are zero.
     */
    protected static function hexToBigInteger(string $hex): BigInteger
    {
        $value = ltrim(preg_replace('/^0x/i', '', $hex) ?? '', '0');

        return $value === '' ? BigInteger::zero() : BigInteger::fromBase($value, 16);
    }

    public function gasEstimate(string $from, string $to, string $data): BigDecimal
    {
        $value = $this->rpc('eth_estimateGas', [
            [
                'from' => $from,
                'to' => $to,
                'data' => $data
            ]
        ]);

        return static::hexToBigDecimal($value);
    }

    public function previewTransfer(
        string $from,
        string $to,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?int $gasLimit = null
    ): PreviewTransferDTO {
        $from = Str::lower($from);
        $to = Str::lower($to);

        $data = '0x'.static::bigDecimalToHex($amount->multipliedBy(pow(10, 18)));

        $gasPrice = $this->gasPrice();
        $gasEstimate = $this->gasEstimate($from, $to, $data);
        if( $gasLimit ) {
            $gasLimit = BigDecimal::of($gasLimit);
            $gasEstimate = $gasLimit->isLessThan($gasEstimate) ? $gasLimit : $gasEstimate;
        }
        $fee = $gasPrice
            ->multipliedBy($gasEstimate)
            ->dividedBy(pow(10, 18), 18);

        if ($balanceBefore === null) {
            $balanceBefore = $this->getBalance($from);
        }
        $balanceAfter = $balanceBefore->minus($fee)->minus($amount);

        $error = null;
        if ($balanceAfter->isNegative()) {
            $error = 'Недостаточно баланса ETH';
        }

        return PreviewTransferDTO::make([
            'from' => $from,
            'to' => $to,
            'amount' => $amount->__toString(),
            'data' => $data,
            'gas_price' => $gasPrice->__toString(),
            'gas_limit' => $gasEstimate->__toString(),
            'fee' => $fee->__toString(),
            'balance_before' => $balanceBefore->__toString(),
            'balance_after' => $balanceAfter->__toString(),
            'error' => $error,
        ]);
    }

    public function transfer(
        string $from,
        string $to,
        string $privateKey,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?int $gasLimit = null
    ): TransferDTO {
        $preview = $this->previewTransfer($from, $to, $amount, $balanceBefore, $gasLimit);
        if( $preview->hasError() ) {
            throw new \Exception($preview->error());
        }

        $gasPrice = static::bigDecimalToHex($preview->gasPrice());
        $gasLimit = static::bigDecimalToHex($preview->gasLimit());

        $txid = $this->sendWithSafeNonce($from, function (int $nonce) use ($preview, $gasPrice, $gasLimit, $amount, $privateKey): string {
            $tx = new Transaction(
                nonce: dechex($nonce),
                gasPrice: $gasPrice,
                gasLimit: $gasLimit,
                to: $preview->to(),
                value: '0x'.static::bigDecimalToHex($amount->multipliedBy(pow(10, 18))),
                data: ''
            );

            return '0x'.$tx->getRaw($privateKey, 1);
        }, $nonce);

        return TransferDTO::make([
            ...$preview->toArray(),
            'txid' => $txid,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Preview of an arbitrary transaction: native value plus call data, as requested by a dApp
     * (WalletConnect eth_sendTransaction). Without $gasLimit the node estimate is padded by
     * `ethereum.call_gas_buffer` (default 20%). A call the node says would revert comes back
     * with `error` instead of throwing.
     */
    public function previewCall(
        string $from,
        string $to,
        BigDecimal $valueWei,
        string $data = '',
        ?int $gasLimit = null,
        ?BigDecimal $balanceBefore = null,
    ): PreviewTransferDTO {
        $from = Str::lower($from);
        $to = Str::lower($to);
        $data = $data === '' || $data === '0x' ? '' : '0x'.Str::lower(preg_replace('/^0x/i', '', $data));
        $valueWei = $valueWei->toScale(0);
        $amount = $valueWei->withPointMovedLeft(18);

        if ($balanceBefore === null) {
            $balanceBefore = $this->getBalance($from);
        }

        $gasPrice = $this->gasPrice();
        $error = null;

        try {
            if ($gasLimit) {
                $gas = BigDecimal::of($gasLimit);
            } else {
                $params = ['from' => $from, 'to' => $to];
                if ($data !== '') {
                    $params['data'] = $data;
                }
                if (! $valueWei->isZero()) {
                    $params['value'] = '0x'.ltrim(static::bigDecimalToHex($valueWei), '0');
                }

                $gas = static::hexToBigDecimal($this->rpc('eth_estimateGas', [$params]))
                    ->multipliedBy((string) config('ethereum.call_gas_buffer', '1.2'))
                    ->toScale(0, RoundingMode::Up);
            }
        } catch (\Exception $e) {
            $gas = BigDecimal::zero();
            $error = 'Transaction would fail: '.$e->getMessage();
        }

        $fee = $gasPrice->multipliedBy($gas)->dividedBy(pow(10, 18), 18);
        $balanceAfter = $balanceBefore->minus($fee)->minus($amount);

        if ($error === null && $balanceAfter->isNegative()) {
            $error = 'Недостаточно баланса ETH';
        }

        return PreviewTransferDTO::make([
            'from' => $from,
            'to' => $to,
            'amount' => $amount->__toString(),
            'data' => $data,
            'gas_price' => $gasPrice->__toString(),
            'gas_limit' => $gas->__toString(),
            'fee' => $fee->__toString(),
            'balance_before' => $balanceBefore->__toString(),
            'balance_after' => $balanceAfter->__toString(),
            'error' => $error,
        ]);
    }

    /**
     * Sign and broadcast an arbitrary transaction (see previewCall()).
     */
    public function sendCall(
        string $from,
        string $to,
        string $privateKey,
        BigDecimal $valueWei,
        string $data = '',
        ?int $gasLimit = null,
        ?BigDecimal $balanceBefore = null,
    ): TransferDTO {
        $preview = $this->previewCall($from, $to, $valueWei, $data, $gasLimit, $balanceBefore);
        if ($preview->hasError()) {
            throw new \Exception($preview->error());
        }

        $gasPriceHex = $preview->gasPrice()->toBigInteger()->toBase(16);
        $gasLimitHex = $preview->gasLimit()->toBigInteger()->toBase(16);
        $valueHex = $valueWei->isZero() ? '' : '0x'.static::bigDecimalToHex($valueWei->toScale(0));

        $txid = $this->sendWithSafeNonce($preview->from(), function (int $nonce) use ($preview, $gasPriceHex, $gasLimitHex, $valueHex, $privateKey): string {
            $tx = new Transaction(
                nonce: dechex($nonce),
                gasPrice: $gasPriceHex,
                gasLimit: $gasLimitHex,
                to: $preview->to(),
                value: $valueHex,
                data: $preview->data()
            );

            return '0x'.$tx->getRaw($privateKey, 1);
        }, $nonce);

        return TransferDTO::make([
            ...$preview->toArray(),
            'txid' => $txid,
            'nonce' => $nonce,
        ]);
    }

    public function previewTokenTransfer(
        string $contract,
        string $from,
        string $to,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): PreviewTransferDTO {
        $contract = Str::lower($contract);
        $from = Str::lower($from);
        $to = Str::lower($to);

        $decimals = $this->tokenDecimals[$contract] ??= $this->getTokenDecimals($contract);

        if ($tokenBalanceBefore === null) {
            $tokenBalanceBefore = $this->getBalanceOfToken($from, $contract);
        }
        $tokenBalanceAfter = $tokenBalanceBefore->minus($amount);

        if ($balanceBefore === null) {
            $balanceBefore = $this->getBalance($from);
        }

        if ($tokenBalanceAfter->isNegative()) {
            return PreviewTransferDTO::make([
                'contract' => $contract,
                'from' => $from,
                'to' => $to,
                'amount' => $amount->__toString(),
                'data' => '',
                'gas_price' => 0,
                'gas_limit' => 0,
                'fee' => 0,
                'balance_before' => $balanceBefore->__toString(),
                'balance_after' => $balanceBefore->__toString(),
                'token_balance_before' => $tokenBalanceBefore->__toString(),
                'token_balance_after' => $tokenBalanceAfter->__toString(),
                'error' => 'Недостаточно баланса токена',
            ]);
        }

        $data = '0xa9059cbb000000000000000000000000'.substr($to, 2).str_pad(
                $amount->multipliedBy(pow(10, $decimals))->toBigInteger()->toBase(16),
                64,
                '0',
                STR_PAD_LEFT
            );

        $gasPrice = $this->gasPrice();
        $gasEstimate = $this->gasEstimate($from, $contract, $data);
        if( $gasLimit ) {
            $gasLimit = BigDecimal::of($gasLimit);
            $gasEstimate = $gasLimit->isLessThan($gasEstimate) ? $gasLimit : $gasEstimate;
        }
        $fee = $gasPrice
            ->multipliedBy($gasEstimate)
            ->dividedBy(pow(10, 18), 18);
        $balanceAfter = $balanceBefore->minus($fee);

        $error = null;
        if ($balanceAfter->isNegative()) {
            $error = 'Недостаточно баланса ETH';
        }

        return PreviewTransferDTO::make([
            'contract' => $contract,
            'from' => $from,
            'to' => $to,
            'amount' => $amount->__toString(),
            'data' => $data,
            'gas_price' => $gasPrice->__toString(),
            'gas_limit' => $gasEstimate->__toString(),
            'fee' => $fee->__toString(),
            'balance_before' => $balanceBefore->__toString(),
            'balance_after' => $balanceAfter->__toString(),
            'token_balance_before' => $tokenBalanceBefore->__toString(),
            'token_balance_after' => $tokenBalanceAfter->__toString(),
            'error' => $error,
        ]);
    }

    public function transferToken(
        string $contract,
        string $from,
        string $to,
        string $privateKey,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null,
    ): TransferDTO {
        $preview = $this->previewTokenTransfer($contract, $from, $to, $amount, $balanceBefore, $tokenBalanceBefore, $gasLimit);
        if( $preview->hasError() ) {
            throw new \Exception($preview->error());
        }

        $gasPrice = $preview->gasPrice()->toBigInteger()->toBase(16);
        $gasLimit = $preview->gasLimit()->toBigInteger()->toBase(16);

        $txid = $this->sendWithSafeNonce($from, function (int $nonce) use ($preview, $gasPrice, $gasLimit, $privateKey): string {
            $tx = new Transaction(
                nonce: dechex($nonce),
                gasPrice: $gasPrice,
                gasLimit: $gasLimit,
                to: $preview->contract(),
                value: '',
                data: $preview->data()
            );

            return '0x'.$tx->getRaw($privateKey, 1);
        }, $nonce);

        return TransferDTO::make([
            ...$preview->toArray(),
            'txid' => $txid,
            'nonce' => $nonce,
        ]);
    }

    public function previewTransferFromToken(
        string $contract,
        string $from,
        string $to,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null
    ): PreviewTransferDTO {
        $contract = Str::lower($contract);
        $from = Str::lower($from);
        $to = Str::lower($to);

        // Получаем количество десятичных знаков для токена
        $decimals = $this->tokenDecimals[$contract] ??= $this->getTokenDecimals($contract);

        // Если баланс токенов не передан, получаем его
        if ($tokenBalanceBefore === null) {
            $tokenBalanceBefore = $this->getBalanceOfToken($from, $contract);
        }

        $tokenBalanceAfter = $tokenBalanceBefore->minus($amount);

        // Если баланс ETH не передан, получаем его
        if ($balanceBefore === null) {
            $balanceBefore = $this->getBalance($from);
        }

        if ($tokenBalanceAfter->isNegative()) {
            return PreviewTransferDTO::make([
                'contract' => $contract,
                'from' => $from,
                'to' => $to,
                'amount' => $amount->__toString(),
                'data' => '',
                'gas_price' => 0,
                'gas_limit' => 0,
                'fee' => 0,
                'balance_before' => $balanceBefore->__toString(),
                'balance_after' => $balanceBefore->__toString(),
                'token_balance_before' => $tokenBalanceBefore->__toString(),
                'token_balance_after' => $tokenBalanceAfter->__toString(),
                'error' => 'Недостаточно баланса токена',
            ]);
        }

        // Код для метода transferFrom (вместо transfer)
        $data = '0x23b872dd' // Хеш для transferFrom
            .substr($from, 2)  // Адрес отправителя
            .substr($to, 2)    // Адрес получателя
            .str_pad(
                $amount->multipliedBy(pow(10, $decimals))->toBigInteger()->toBase(16),
                64,
                '0',
                STR_PAD_LEFT
            );

        $gasPrice = $this->gasPrice();
        $gasEstimate = $this->gasEstimate($from, $contract, $data);
        if( $gasLimit ) {
            $gasLimit = BigDecimal::of($gasLimit);
            $gasEstimate = $gasLimit->isLessThan($gasEstimate) ? $gasLimit : $gasEstimate;
        }
        $fee = $gasPrice
            ->multipliedBy($gasEstimate)
            ->dividedBy(pow(10, 18), 18);
        $balanceAfter = $balanceBefore->minus($fee);

        $error = null;
        if ($balanceAfter->isNegative()) {
            $error = 'Недостаточно баланса ETH';
        }

        return PreviewTransferDTO::make([
            'contract' => $contract,
            'from' => $from,
            'to' => $to,
            'amount' => $amount->__toString(),
            'data' => $data,
            'gas_price' => $gasPrice->__toString(),
            'gas_limit' => $gasEstimate->__toString(),
            'fee' => $fee->__toString(),
            'balance_before' => $balanceBefore->__toString(),
            'balance_after' => $balanceAfter->__toString(),
            'token_balance_before' => $tokenBalanceBefore->__toString(),
            'token_balance_after' => $tokenBalanceAfter->__toString(),
            'error' => $error,
        ]);
    }

    public function transferFromToken(
        string $contract,
        string $from,
        string $to,
        string $privateKey,
        BigDecimal $amount,
        ?BigDecimal $balanceBefore = null,
        ?BigDecimal $tokenBalanceBefore = null,
        ?int $gasLimit = null
    ): TransferDTO {
        // Предварительная проверка параметров перевода
        $preview = $this->previewTransferFromToken($contract, $from, $to, $amount, $balanceBefore, $tokenBalanceBefore, $gasLimit);
        if ($preview->hasError()) {
            throw new \Exception($preview->error());
        }

        $gasPrice = $preview->gasPrice()->toBigInteger()->toBase(16);
        $gasLimit = $preview->gasLimit()->toBigInteger()->toBase(16);

        // Формируем, подписываем и отправляем транзакцию с безопасным nonce
        $txid = $this->sendWithSafeNonce($from, function (int $nonce) use ($preview, $gasPrice, $gasLimit, $privateKey): string {
            $tx = new Transaction(
                nonce: dechex($nonce),
                gasPrice: $gasPrice,
                gasLimit: $gasLimit,
                to: $preview->contract(),
                value: '',
                data: $preview->data()
            );

            return '0x' . $tx->getRaw($privateKey, 1);
        });

        return TransferDTO::make([
            ...$preview->toArray(),
            'txid' => $txid,
        ]);
    }
}
