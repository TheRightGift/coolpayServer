# CoolPay Backend (Laravel) – QR Payments with Company Settlement

## What this service does
- Tokenized QR pay links: receivers generate tokens; payers scan to pay via app or web.
- App→App payments: ledger debit/credit between user wallets (funds stay in company Paystack account).
- Web checkout: Paystack Standard collects funds to company account; credits receiver wallet on webhook.
- Deposits (top-up): Paystack Standard; credits user wallet on webhook.
- Withdrawals: Paystack Transfer from company balance to user bank; debits wallet on init, finalizes on transfer webhook (refunds on fail).
- Idempotency keys supported on deposits, app execute, web checkout, withdrawals.
- Rate limiting on sensitive routes; Paystack signature verification.

## To-do items that must be done after code pull
1) Run migrations: `php artisan migrate`.
2) Configure `.env`:
   - `PAYSTACK_SECRET=...`
   - `APP_URL=https://your-domain` (reachable for webhooks)
3) Configure Paystack webhooks to POST to `https://your-domain/api/webhooks/paystack` (for both charge and transfer events).
4) Register `ReconcilePaystack` command in `app/Console/Kernel.php` (add to `$commands`) and schedule it (e.g., nightly) if desired.
5) Test flows end-to-end (deposit, web checkout, app execute, withdrawal, webhooks, reconcile).
6) Decide refund behavior for payouts if webhooks fail; reconciliation command currently does not auto-refund on failed transfer—it only updates status.

## API quick reference
- Auth: `/auth/login`, `/auth/logout` (Bearer token via Passport).
- Pay links: `POST /api/pay-links` (auth) → `{ token, url, deep_link }`.
- Prepare pay: `GET /api/pay/{token}/prepare` (public, throttled).
- Execute (app→app): `POST /api/pay/{token}/execute` (auth, throttled, idempotent).
- Web checkout: `POST /api/pay/{token}/checkout` (public, throttled, idempotent) → Paystack init.
- Deposits: `POST /api/deposits/init` (auth, idempotent) → Paystack init.
- Withdrawals: `POST /api/wallet/withdraw` (auth, idempotent) → Paystack Transfer.
- Transactions: `GET /api/transactions` (auth, paginated; filters `type`, `status`).
- User payload: `GET /api/user` (auth) → user, wallet, transactions.
- Banks: `GET /api/banks` (auth) → Paystack banks.

## Rate limiting & security
- Auth group: `throttle:60,1`; execute/checkout: `throttle:30,1`; prepare: `throttle:60,1`; webhooks: `throttle:120,1`; login: `throttle:30,1`.
- Paystack webhooks: HMAC SHA512 signature verification.
- Paystack balance check before initiating transfers.

## Reconciliation
- Command: `php artisan reconcile:paystack --limit=50`
  - Verifies pending deposits (DEP-*) and web checkouts (WEB-*) via Paystack verify.
  - Verifies pending payouts (WD-*) via Paystack transfer lookup.
  - Marks success/failed and stamps `reconciled_at` in transaction meta. (Does **not** refund on failed payout; webhook already handles refund on fail.)

## Testing (PHPUnit)
- Install deps: `composer install` (include dev).
- Run all tests: `./vendor/bin/phpunit`.
- Feature tests cover: pay link prepare, app execute ledger debit/credit, deposit init (stub), web checkout init (stub), withdrawal (stub), webhooks charge success credit, transfer failed refund.
- Tests run with `APP_ENV=testing` which stubs external Paystack calls; real HTTP is not hit.

## Deployment steps
1) Clone/pull repo to target environment.
2) Install PHP deps: `composer install --no-dev --optimize-autoloader` (for prod).
3) Copy `.env.example` to `.env` and set:
   - `APP_KEY` (run `php artisan key:generate`)
   - `APP_URL`
   - `DB_*` settings
   - `PAYSTACK_SECRET`
   - Any cache/queue/mail configs as needed
4) Run migrations: `php artisan migrate --force`.
5) Cache config/routes: `php artisan config:cache && php artisan route:cache`.
6) Set web server to point to `public/` (Nginx/Apache) and ensure HTTPS is enabled.
7) Configure Paystack webhook URL to `https://your-domain/api/webhooks/paystack`.
8) Register and schedule reconciliation command in `app/Console/Kernel.php` and your OS scheduler (cron): e.g., `php artisan reconcile:paystack --limit=100` nightly.
9) Ensure queue/worker setup if you offload webhooks/notifications (optional; current code is synchronous).

## Notes
- All funds settle to the company Paystack account; wallets are internal ledger balances.
- Direction metadata (`user_payment`, `deposit_funding`, `payout`) + webhook_events are stored in transaction meta for audit.
- Idempotency keys: set `Idempotency-Key` header on deposits, execute, checkout, and withdraw to avoid duplicates.
- Testing environment stubs external Paystack calls; production uses live HTTP to Paystack.
