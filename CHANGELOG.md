# Changelog

All notable changes to `laravel-ethereum` will be documented in this file.

## v1.0.2 - 2026-06-05

### Fixed

- Nonce collision on sequential transfers: `transfer()`, `transferToken()` and `transferFromToken()` now serialize sends per address (cache lock) and reserve the next nonce locally (`max(node pending count, reserved nonce)`), so rapid consecutive transactions no longer replace each other in the mempool.
