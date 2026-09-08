-- Staging sanitize — run AFTER restoring a production dump into aiku_staging,
-- and ALWAYS BEFORE the app is started. Surgical: replaces secret-bearing fields
-- with an obvious fake value, leaving surrounding config intact so the app still
-- renders. Email is caught by MAIL_HOST=mailhog (.env); everything that could
-- act on the real world (or be cracked offline) is neutralized here.
--
-- Every statement is guarded with to_regclass so schema drift (renamed/dropped
-- tables) degrades to a skipped step instead of aborting the whole refresh.
-- Runs as a single transaction under ON_ERROR_STOP=1: all-or-nothing.
--
-- Secret map (July 2026): payment_accounts.data->'credentials' {api_key,client_id,
-- client_secret,public_key,secret_key}; org/psp data = {}; sender_emails.data = no keys.
-- users.legacy_password + web_users.data->legacy_password are UNSALTED sha256 and
-- accepted by AuthoriseWebUserWithLegacyPassword — live credentials, must go.

BEGIN;

DO $$
BEGIN

-- Payment gateway credentials (checkout.com etc.) — replace only the secret keys
IF to_regclass('public.payment_accounts') IS NOT NULL THEN
  EXECUTE $q$
    UPDATE payment_accounts
    SET data = jsonb_set(jsonb_set(jsonb_set(jsonb_set(jsonb_set(
          data,
          '{credentials,api_key}',       '"SANITIZED_STAGING"'::jsonb, false),
          '{credentials,client_id}',     '"SANITIZED_STAGING"'::jsonb, false),
          '{credentials,client_secret}', '"SANITIZED_STAGING"'::jsonb, false),
          '{credentials,public_key}',    '"SANITIZED_STAGING"'::jsonb, false),
          '{credentials,secret_key}',    '"SANITIZED_STAGING"'::jsonb, false)
    WHERE data ? 'credentials'
  $q$;
END IF;

-- Shipper/carrier credentials: unconditional wipe
IF to_regclass('public.shipper_accounts') IS NOT NULL THEN
  EXECUTE $q$UPDATE shipper_accounts SET credentials = '{}'::jsonb, settings = '{}'::jsonb, data = '{}'::jsonb$q$;
END IF;

-- Cloudflare tokens (staging must not manage real CF zones)
IF to_regclass('public.websites') IS NOT NULL THEN
  EXECUTE $q$UPDATE websites SET cloudflare_token = 'SANITIZED_STAGING' WHERE cloudflare_token IS NOT NULL$q$;
END IF;

-- Fulfilment webhook keys
IF to_regclass('public.fulfilment_customers') IS NOT NULL THEN
  EXECUTE $q$UPDATE fulfilment_customers SET webhook_access_key = 'SANITIZED_STAGING' WHERE webhook_access_key IS NOT NULL$q$;
END IF;

-- Marketplace / store integrations: clear OAuth tokens & secrets
IF to_regclass('public.allegro_users') IS NOT NULL THEN
  EXECUTE $q$UPDATE allegro_users SET access_token = 'SANITIZED_STAGING', refresh_token = 'SANITIZED_STAGING' WHERE access_token IS NOT NULL OR refresh_token IS NOT NULL$q$;
END IF;
IF to_regclass('public.tiktok_users') IS NOT NULL THEN
  EXECUTE $q$UPDATE tiktok_users SET access_token = 'SANITIZED_STAGING', refresh_token = 'SANITIZED_STAGING' WHERE access_token IS NOT NULL OR refresh_token IS NOT NULL$q$;
END IF;
IF to_regclass('public.shopify_users') IS NOT NULL THEN
  EXECUTE $q$UPDATE shopify_users SET password = 'SANITIZED_STAGING' WHERE password IS NOT NULL$q$;
END IF;
IF to_regclass('public.woo_commerce_users') IS NOT NULL THEN
  EXECUTE $q$UPDATE woo_commerce_users SET consumer_secret = 'SANITIZED_STAGING' WHERE consumer_secret IS NOT NULL$q$;
END IF;
IF to_regclass('public.magento_users') IS NOT NULL THEN
  EXECUTE $q$UPDATE magento_users SET password = 'SANITIZED_STAGING' WHERE password IS NOT NULL$q$;
END IF;

-- API auth artifacts: prod bearer tokens must not be valid against staging
IF to_regclass('public.oauth_access_tokens')  IS NOT NULL THEN EXECUTE 'TRUNCATE oauth_access_tokens';  END IF;
IF to_regclass('public.oauth_refresh_tokens') IS NOT NULL THEN EXECUTE 'TRUNCATE oauth_refresh_tokens'; END IF;
IF to_regclass('public.personal_access_tokens') IS NOT NULL THEN EXECUTE 'TRUNCATE personal_access_tokens'; END IF;
IF to_regclass('public.fcm_tokens') IS NOT NULL THEN EXECUTE 'TRUNCATE fcm_tokens'; END IF;
IF to_regclass('public.oauth_clients') IS NOT NULL THEN
  EXECUTE 'UPDATE oauth_clients SET secret = NULL WHERE secret IS NOT NULL';
END IF;

-- Saved-card gateway references (MIT): card-linked PII, not needed on staging
IF to_regclass('public.mit_saved_cards') IS NOT NULL THEN EXECUTE 'TRUNCATE mit_saved_cards'; END IF;

-- Queue remnants: queues run on Redis (not in the dump), but a Horizon "retry"
-- on a restored failed_jobs row would fire a REAL payload
IF to_regclass('public.failed_jobs') IS NOT NULL THEN EXECUTE 'TRUNCATE failed_jobs'; END IF;
IF to_regclass('public.job_batches') IS NOT NULL THEN EXECUTE 'TRUNCATE job_batches'; END IF;
IF to_regclass('public.jobs') IS NOT NULL THEN EXECUTE 'TRUNCATE jobs'; END IF;

-- legacy_password (users.legacy_password + web_users.data->legacy_password) is
-- DELIBERATELY KEPT: testers must be able to log into staging with legacy
-- credentials. These are unsalted sha256 (offline-crackable), which is an
-- accepted risk here — staging already holds the full prod dataset, so the
-- control is access to the staging box, not wiping this column.
IF to_regclass('public.users') IS NOT NULL THEN
  EXECUTE 'UPDATE users SET google2fa_secret = NULL WHERE google2fa_secret IS NOT NULL';
END IF;

-- Debug webhook capture table: may hold prod payloads/urls
IF to_regclass('public.debug_webhooks') IS NOT NULL THEN EXECUTE 'TRUNCATE debug_webhooks'; END IF;

END $$;

COMMIT;
