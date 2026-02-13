# CoolPay Backend (Laravel) – QR Payments with Company Settlement

## What this service does
- Tokenized QR pay links: receivers generate tokens; payers scan to pay via app or web.
- App→App payments: ledger debit/credit between user wallets (funds stay in company Paystack account).
- Web checkout: Paystack Standard collects funds to company account; credits receiver wallet on webhook.
- Deposits (top-up): Paystack Standard; credits user wallet on webhook.
- Withdrawals: Paystack Transfer from company balance to user bank; debits wallet on init, finalizes on transfer webhook (refunds on fail).
- 2FA setup + verification and login challenge flow are supported.
- Idempotency keys supported on deposits, app execute, web checkout, withdrawals.
- Rate limiting on sensitive routes; Paystack signature verification.

## Deployment checklist (production)
1) Clone/pull repo to target environment.
2) Install dependencies:
   - `composer install --no-dev --optimize-autoloader`
3) Configure environment:
   - `cp .env.example .env`
   - `php artisan key:generate`
   - Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   - Set DB credentials (`DB_*`)
   - Set `PAYSTACK_SECRET`
   - Configure mail settings for password reset
4) Run migrations:
   - `php artisan migrate --force`
5) Build/cache runtime:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
6) Web server:
   - Serve from `public/`
   - Enforce HTTPS and redirect HTTP→HTTPS
7) Configure Paystack webhook:
   - `https://your-domain/api/webhooks/paystack`
8) Schedule reconciliation:
   - `php artisan reconcile:paystack --limit=100` (e.g., nightly)
9) Set process supervision:
   - PHP-FPM/Apache/Nginx health checks
   - Optional queue worker supervision if async jobs are introduced

## Security hardening notes
- Keep `APP_DEBUG=false` in production.
- Rotate `APP_KEY`, API secrets, and Paystack secret via secure secret manager.
- Restrict DB and app ports at network/firewall layer.
- Do not expose `.env`, storage logs, or debug endpoints publicly.
- Keep webhook signature verification enabled (HMAC SHA512).
- Keep transaction mutation endpoints closed; state changes should flow through payment/deposit/withdrawal handlers only.
- 2FA:
  - Setup requires OTP verification before enable is finalized.
  - Login for 2FA-enabled users requires challenge + OTP verification.

## Availability / reliability guidance
- Use a managed DB with backups + PITR where possible.
- Monitor:
  - webhook failures
  - pending transaction backlog
  - reconciliation results
  - payout failure rate
- Reconciliation command is idempotent-safe and applies wallet side-effects for pending records.
- Add uptime checks on:
  - `/up`
  - API auth endpoint
  - webhook endpoint reachability (synthetic)

## API quick reference
- Web auth: `/auth/login`, `/auth/logout`, `/auth/2fa/verify-login`
- API auth: `/api/auth/login`, `/api/auth/logout`, `/api/auth/register`
- Password reset:
  - `/api/auth/forgot-password`
  - `/api/auth/reset-password-token`
  - `/api/auth/reset-password` (authenticated)
- 2FA management (authenticated):
  - `/api/2fa/enable`
  - `/api/2fa/verify`
  - `/api/2fa/disable`
- Pay links: `POST /api/pay-links` (auth) → `{ token, url, deep_link }`
- Prepare pay: `GET /api/pay/{token}/prepare` (public, throttled)
- Execute (app→app): `POST /api/pay/{token}/execute` (auth, throttled, idempotent)
- Web checkout: `POST /api/pay/{token}/checkout` (public, throttled, idempotent)
- Deposits: `POST /api/deposits/init` (auth, idempotent)
- Withdrawals: `POST /api/wallet/withdraw` (auth, idempotent)
- Transactions: `GET /api/transactions` (auth; filters: `type`, `status`)
- User payload: `GET /api/user` (auth)
- Banks: `GET /api/banks` (auth)

## Reconciliation
- Command: `php artisan reconcile:paystack --limit=50`
  - Verifies pending deposits (DEP-*) and web checkouts (WEB-*) via Paystack verify
  - Verifies pending payouts (WD-*) via Paystack transfer lookup
  - Updates status + `reconciled_at`
  - Applies wallet side-effects where required (credit/refund on pending transitions)

## Testing notes
- Install deps: `composer install`
- Run tests: `./vendor/bin/phpunit`
- If tests fail with SQLite driver errors, install/enable `pdo_sqlite` for CLI PHP.
- Test environment stubs external Paystack calls (`APP_ENV=testing`).

## Operational notes
- All funds settle to company Paystack account; wallets are internal ledger balances.
- Direction metadata (`user_payment`, `deposit_funding`, `payout`) + webhook events are stored in transaction meta for audit.
- Always send `Idempotency-Key` on deposits, execute, checkout, withdraw to prevent duplicates.
