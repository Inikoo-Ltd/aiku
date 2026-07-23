-- Staging sanitize — run AFTER restoring a production dump into aiku_staging.
-- Surgical: replaces only the secret-bearing fields with an obvious fake value,
-- leaving surrounding config intact so the app still renders. Email is caught by
-- MAIL_HOST=mailhog (.env), so customer addresses are left as-is.
--
-- Secret locations were mapped from the live schema:
--   payment_accounts.data->'credentials' = {api_key,client_id,client_secret,public_key,secret_key}
--   org_payment_service_providers.data / payment_service_providers.data = empty {} (no secrets)
--   shipper_accounts = 0 rows (nothing to do)

BEGIN;

-- Payment gateway credentials (checkout.com etc.) — replace only the secret keys
UPDATE payment_accounts
SET data = jsonb_set(jsonb_set(jsonb_set(jsonb_set(jsonb_set(
      data,
      '{credentials,api_key}',       '"SANITIZED_STAGING"'::jsonb, false),
      '{credentials,client_id}',     '"SANITIZED_STAGING"'::jsonb, false),
      '{credentials,client_secret}', '"SANITIZED_STAGING"'::jsonb, false),
      '{credentials,public_key}',    '"SANITIZED_STAGING"'::jsonb, false),
      '{credentials,secret_key}',    '"SANITIZED_STAGING"'::jsonb, false)
WHERE data ? 'credentials';

-- Cloudflare tokens (staging must not manage real CF zones)
UPDATE websites SET cloudflare_token = 'SANITIZED_STAGING' WHERE cloudflare_token IS NOT NULL;

-- Fulfilment webhook keys
UPDATE fulfilment_customers SET webhook_access_key = 'SANITIZED_STAGING' WHERE webhook_access_key IS NOT NULL;

-- Marketplace / store integration secrets (staging must not call real stores)
UPDATE allegro_users      SET access_token = 'SANITIZED_STAGING', refresh_token = 'SANITIZED_STAGING'
  WHERE access_token IS NOT NULL OR refresh_token IS NOT NULL;
UPDATE tiktok_users       SET access_token = 'SANITIZED_STAGING', refresh_token = 'SANITIZED_STAGING'
  WHERE access_token IS NOT NULL OR refresh_token IS NOT NULL;
UPDATE shopify_users      SET password = 'SANITIZED_STAGING' WHERE password IS NOT NULL;
UPDATE woo_commerce_users SET consumer_secret = 'SANITIZED_STAGING' WHERE consumer_secret IS NOT NULL;
UPDATE magento_users      SET password = 'SANITIZED_STAGING' WHERE password IS NOT NULL;

-- Clear 2FA secrets so they don't lock testers out of staging logins.
-- (User password hashes are bcrypt — safe — left intact; reset a specific admin
--  with `php artisan user:password <user> <pass>` after refresh if needed.)
UPDATE users SET google2fa_secret = NULL WHERE google2fa_secret IS NOT NULL;

-- Debug webhook capture table: may hold prod payloads/urls
TRUNCATE debug_webhooks;

COMMIT;
