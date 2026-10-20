<?php

namespace ItHealer\LaravelEthereum\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use ItHealer\LaravelEthereum\Enums\EthereumModel;
use ItHealer\LaravelEthereum\Facades\Ethereum;
use ItHealer\LaravelEthereum\Models\EthereumAddress;
use ItHealer\LaravelEthereum\Services\Sync\AddressSync;

/**
 * One-off backfill of internal transfers synced before they were supported. Safe to repeat:
 * rows are matched by transaction, so nothing is duplicated.
 */
class SyncInternalTransactionsCommand extends Command
{
    protected $signature = 'ethereum:sync-internal
        {--address= : Address id (all available addresses by default)}
        {--from-block=0 : Read from this block}
        {--webhooks : Also call the deposit webhook handler for incoming transfers found (merchant, notifications)}';

    protected $description = 'Read internal transfers of Ethereum addresses once more (backfill of the history)';

    public function handle(): int
    {
        /** @var class-string<EthereumAddress> $model */
        $model = Ethereum::getModel(EthereumModel::Address);

        $addresses = $model::query()
            ->where('available', true)
            ->when($this->option('address'), fn ($query, $id) => $query->whereKey((int)$id))
            ->orderBy('id')
            ->get();

        $total = 0;

        foreach ($addresses as $address) {
            $this->line("-- *{$address->address}*");

            try {
                $service = App::make(AddressSync::class, [
                    'address' => $address,
                    'force' => true,
                ]);

                $service->setLogger(fn (string $message, ?string $type) => $this->{$type ? ($type === 'success' ? 'info' : $type) : 'line'}($message));

                $stored = $service->backfillInternalTransactions((int)$this->option('from-block'), (bool)$this->option('webhooks'));
                $total += $stored;

                $this->info("   internal transfers: {$stored}");
            } catch (\Exception $e) {
                $this->error('   Error: '.$e->getMessage());
            }
        }

        $this->line("-- Completed, internal transfers stored: {$total}");

        return self::SUCCESS;
    }
}
