# Changelog

All notable changes to `laravel-ethereum` will be documented in this file.

## v1.0.5 - 2026-06-05

### Fixed

- Incoming transactions could be lost forever when the explorer indexed them later than the node head: address sync now keeps a configurable overlap (`ethereum.sync.lag_blocks`, default 20 blocks) behind the current block instead of jumping `sync_block_number` straight to the node head.

## v1.0.4 - 2026-06-05

### Changed

- Allow Laravel 13 (`illuminate/contracts: ^13.0`).


## v1.0.3 - 2026-06-05

### Fixed

- Nonce collision on sequential transfers: `transfer()`, `transferToken()` and `transferFromToken()` now serialize sends per address (cache lock) and reserve the next nonce locally (`max(node pending count, reserved nonce)`), so rapid consecutive transactions no longer replace each other in the mempool.
