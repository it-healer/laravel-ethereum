# Changelog

All notable changes to `laravel-ethereum` will be documented in this file.

## v1.2.0 - 2026-06-15

### Added

- Alchemy support: driver-based explorer (`alchemy_getAssetTransfers`) selectable via
  `ethereum_explorers.driver`, `Ethereum::createAlchemyNode()` / `createAlchemyExplorer()`.
- Compute Unit (CU) metering on nodes/explorers (monthly reset) with least-credits selection;
  `ethereum.compute_units` overrides; `ethereum.sync.track_outgoing` toggle.
- Alchemy Notify (Address Activity webhooks): receiver route, signature verification, targeted
  sync job, optional auto-subscribe, and commands `ethereum:alchemy-setup`,
  `ethereum:alchemy-reconcile`, `ethereum:confirm-deposits`. New `ethereum_alchemy_webhooks` table;
  `driver`/`credits`/`credits_at` columns added to explorers/nodes.

## v1.1.0 - 2026-06-15

### Added

- Adaptive (touch-based) synchronization. `ethereum.touch` now supports `fast_interval` (max sync
  frequency while an address is active) and `slow_interval` (while idle; `null` = skip idle
  addresses). `waiting_seconds` is the active window after the last `touch_at`. Addresses are
  polled often while in use and rarely while idle. Defaults preserve the previous behavior.

## v1.0.8 - 2026-06-11

### Added

- Custom BIP-44 derivation path per wallet: `createWallet()` / `generateWallet()` / `importWallet()` / `newWallet()` accept an optional `derivationPath` template (`{index}` placeholder), stored in the new `ethereum_wallets.derivation_path` column. Presets: `Ethereum::PATH_BIP44` (default, MetaMask), `Ethereum::PATH_LEDGER_LIVE`, `Ethereum::PATH_LEDGER_LEGACY`. The default template is configurable via `ethereum.wallet.default_derivation_path`.
- `Ethereum::resolveDerivationPath()` and `Ethereum::validateDerivationPath()` helpers; `createAddress()` / `newAddress()` accept a one-off `derivationPath` override.

### Notes

- Fully backward compatible: the new parameters are appended last and optional, the column defaults to the standard `m/44'/60'/0'/0/{index}`, and deriving by the full path produces byte-identical keys to the previous `derive("m/44'/60'/0'/0")->deriveChild($index)` implementation.

## v1.0.7 - 2026-06-05

### Added

- `BaseSync::onProgress(fn(int \$processed, string \$stage))` and `BaseSync::cancelWhen(fn(): bool)` hooks; `AddressSync` reports progress per processed transaction and aborts with `SyncCancelledException` when the cancel callback returns true (without advancing `sync_block_number`).

## v1.0.6 - 2026-06-05

### Fixed

- Etherscan API V2 support: every explorer request now sends `chainid` (configurable via `ethereum.explorer.chain_id`, default 1). Use `https://api.etherscan.io/v2/api` as the explorer base URL — the V1 endpoint has been shut down by Etherscan.
- Explorer API errors (deprecated endpoint, invalid key, rate limits) now throw instead of being silently treated as an empty transaction list, which combined with the sync window advance caused deposits to be lost.

## v1.0.5 - 2026-06-05

### Fixed

- Incoming transactions could be lost forever when the explorer indexed them later than the node head: address sync now keeps a configurable overlap (`ethereum.sync.lag_blocks`, default 20 blocks) behind the current block instead of jumping `sync_block_number` straight to the node head.

## v1.0.4 - 2026-06-05

### Changed

- Allow Laravel 13 (`illuminate/contracts: ^13.0`).


## v1.0.3 - 2026-06-05

### Fixed

- Nonce collision on sequential transfers: `transfer()`, `transferToken()` and `transferFromToken()` now serialize sends per address (cache lock) and reserve the next nonce locally (`max(node pending count, reserved nonce)`), so rapid consecutive transactions no longer replace each other in the mempool.
