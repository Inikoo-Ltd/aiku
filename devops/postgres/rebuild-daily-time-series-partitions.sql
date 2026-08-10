-- INI-1904: rebuild the three big daily time series partitions without their all zero rows.
--
-- Run ONLY after the sparse time series code is deployed, otherwise the workers refill what this drops.
-- Run with the sales_slave and sales_slave_historic workers paused: rows written between the copy and
-- the swap are not carried over.
--
-- Each block is independent, run them one at a time. psql -v ON_ERROR_STOP=1 -f this-file is fine too.
-- The _old tables are kept on purpose, drop them once the page has been checked.
--
-- Rehearsed on a full size copy against ostsr_quarterly: all 7 partitioned indexes were matched and
-- attached, the foreign key was reused rather than revalidated, and the swap transaction took 13 ms.
-- The rebuilt partition keeps its indexes named ostsr_daily_new_*, which is cosmetic only.

\timing on

-- ---------------------------------------------------------------------------------------------
-- org_stock  ->  ostsr_daily
-- ---------------------------------------------------------------------------------------------

CREATE TABLE ostsr_daily_new (LIKE ostsr_daily INCLUDING DEFAULTS INCLUDING STORAGE);

INSERT INTO ostsr_daily_new
SELECT * FROM ostsr_daily
WHERE NOT (
    coalesce(sales_external, 0) = 0
    AND coalesce(sales_org_currency_external, 0) = 0
    AND coalesce(sales_grp_currency_external, 0) = 0
    AND coalesce(sales_internal, 0) = 0
    AND coalesce(sales_org_currency_internal, 0) = 0
    AND coalesce(sales_grp_currency_internal, 0) = 0
    AND coalesce(lost_revenue, 0) = 0
    AND coalesce(lost_revenue_org_currency, 0) = 0
    AND coalesce(lost_revenue_grp_currency, 0) = 0
    AND coalesce(invoices, 0) = 0
    AND coalesce(refunds, 0) = 0
    AND coalesce(orders, 0) = 0
    AND coalesce(customers_invoiced, 0) = 0
    AND coalesce(cogs_org_currency, 0) = 0
    AND coalesce(cogs_grp_currency, 0) = 0
);

-- Lets ATTACH skip the full scan that would otherwise prove every row belongs in the 'D' partition.
ALTER TABLE ostsr_daily_new ADD CONSTRAINT ostsr_daily_new_frequency_check CHECK (frequency = 'D');

ALTER TABLE ostsr_daily_new ADD PRIMARY KEY (id, frequency);

-- One index per partitioned index on the parent. ATTACH builds any that are missing while holding the
-- lock, so all of them have to exist first.
CREATE INDEX ON ostsr_daily_new ("from");
CREATE INDEX ON ostsr_daily_new ("to");
CREATE INDEX ON ostsr_daily_new (org_stock_time_series_id);
CREATE INDEX ON ostsr_daily_new (org_stock_time_series_id, "from");
CREATE INDEX ON ostsr_daily_new (org_stock_time_series_id, "to");
CREATE UNIQUE INDEX ON ostsr_daily_new (org_stock_time_series_id, period, frequency);

ALTER TABLE ostsr_daily_new
    ADD CONSTRAINT ostsr_daily_new_series_fk FOREIGN KEY (org_stock_time_series_id)
    REFERENCES org_stock_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE;

ANALYZE ostsr_daily_new;

-- The swap. Everything here is metadata, it takes ACCESS EXCLUSIVE on the parent for a moment.
BEGIN;
ALTER TABLE org_stock_time_series_records DETACH PARTITION ostsr_daily;
ALTER TABLE ostsr_daily RENAME TO ostsr_daily_old;
ALTER TABLE ostsr_daily_new RENAME TO ostsr_daily;
ALTER TABLE org_stock_time_series_records ATTACH PARTITION ostsr_daily FOR VALUES IN ('D');
COMMIT;

-- ---------------------------------------------------------------------------------------------
-- trade_unit  ->  tutsr_daily
-- ---------------------------------------------------------------------------------------------

CREATE TABLE tutsr_daily_new (LIKE tutsr_daily INCLUDING DEFAULTS INCLUDING STORAGE);

INSERT INTO tutsr_daily_new
SELECT * FROM tutsr_daily
WHERE NOT (
    coalesce(sales_external, 0) = 0
    AND coalesce(sales_org_currency_external, 0) = 0
    AND coalesce(sales_grp_currency_external, 0) = 0
    AND coalesce(sales_internal, 0) = 0
    AND coalesce(sales_org_currency_internal, 0) = 0
    AND coalesce(sales_grp_currency_internal, 0) = 0
    AND coalesce(lost_revenue, 0) = 0
    AND coalesce(lost_revenue_org_currency, 0) = 0
    AND coalesce(lost_revenue_grp_currency, 0) = 0
    AND coalesce(invoices, 0) = 0
    AND coalesce(refunds, 0) = 0
    AND coalesce(orders, 0) = 0
    AND coalesce(customers_invoiced, 0) = 0
);

ALTER TABLE tutsr_daily_new ADD CONSTRAINT tutsr_daily_new_frequency_check CHECK (frequency = 'D');

ALTER TABLE tutsr_daily_new ADD PRIMARY KEY (id, frequency);

CREATE INDEX ON tutsr_daily_new ("from");
CREATE INDEX ON tutsr_daily_new ("to");
CREATE INDEX ON tutsr_daily_new (trade_unit_time_series_id);
CREATE INDEX ON tutsr_daily_new (trade_unit_time_series_id, "from");
CREATE INDEX ON tutsr_daily_new (trade_unit_time_series_id, "to");
CREATE UNIQUE INDEX ON tutsr_daily_new (trade_unit_time_series_id, period, frequency);

ALTER TABLE tutsr_daily_new
    ADD CONSTRAINT tutsr_daily_new_series_fk FOREIGN KEY (trade_unit_time_series_id)
    REFERENCES trade_unit_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE;

ANALYZE tutsr_daily_new;

BEGIN;
ALTER TABLE trade_unit_time_series_records DETACH PARTITION tutsr_daily;
ALTER TABLE tutsr_daily RENAME TO tutsr_daily_old;
ALTER TABLE tutsr_daily_new RENAME TO tutsr_daily;
ALTER TABLE trade_unit_time_series_records ATTACH PARTITION tutsr_daily FOR VALUES IN ('D');
COMMIT;

-- ---------------------------------------------------------------------------------------------
-- stock  ->  gstsr_daily
-- ---------------------------------------------------------------------------------------------

CREATE TABLE gstsr_daily_new (LIKE gstsr_daily INCLUDING DEFAULTS INCLUDING STORAGE);

INSERT INTO gstsr_daily_new
SELECT * FROM gstsr_daily
WHERE NOT (
    coalesce(sales_external, 0) = 0
    AND coalesce(sales_org_currency_external, 0) = 0
    AND coalesce(sales_grp_currency_external, 0) = 0
    AND coalesce(sales_internal, 0) = 0
    AND coalesce(sales_org_currency_internal, 0) = 0
    AND coalesce(sales_grp_currency_internal, 0) = 0
    AND coalesce(lost_revenue, 0) = 0
    AND coalesce(lost_revenue_org_currency, 0) = 0
    AND coalesce(lost_revenue_grp_currency, 0) = 0
    AND coalesce(invoices, 0) = 0
    AND coalesce(refunds, 0) = 0
    AND coalesce(orders, 0) = 0
    AND coalesce(customers_invoiced, 0) = 0
);

ALTER TABLE gstsr_daily_new ADD CONSTRAINT gstsr_daily_new_frequency_check CHECK (frequency = 'D');

ALTER TABLE gstsr_daily_new ADD PRIMARY KEY (id, frequency);

CREATE INDEX ON gstsr_daily_new ("from");
CREATE INDEX ON gstsr_daily_new ("to");
CREATE INDEX ON gstsr_daily_new (stock_time_series_id);
CREATE INDEX ON gstsr_daily_new (stock_time_series_id, "from");
CREATE INDEX ON gstsr_daily_new (stock_time_series_id, "to");
CREATE UNIQUE INDEX ON gstsr_daily_new (stock_time_series_id, period, frequency);

ALTER TABLE gstsr_daily_new
    ADD CONSTRAINT gstsr_daily_new_series_fk FOREIGN KEY (stock_time_series_id)
    REFERENCES stock_time_series(id) ON DELETE CASCADE ON UPDATE CASCADE;

ANALYZE gstsr_daily_new;

BEGIN;
ALTER TABLE stock_time_series_records DETACH PARTITION gstsr_daily;
ALTER TABLE gstsr_daily RENAME TO gstsr_daily_old;
ALTER TABLE gstsr_daily_new RENAME TO gstsr_daily;
ALTER TABLE stock_time_series_records ATTACH PARTITION gstsr_daily FOR VALUES IN ('D');
COMMIT;

-- ---------------------------------------------------------------------------------------------
-- Checks. Every partitioned index on each parent must have a child on the rebuilt partition,
-- 7 for org_stock and trade_unit, 7 for stock. Anything less means ATTACH did not find a match.
-- ---------------------------------------------------------------------------------------------

SELECT c.relname, count(*) AS indexes
FROM pg_class c
JOIN pg_index i ON i.indrelid = c.oid
WHERE c.relname IN ('ostsr_daily', 'tutsr_daily', 'gstsr_daily')
GROUP BY c.relname;

SELECT c.relname, pg_size_pretty(pg_total_relation_size(c.oid)) AS size, c.reltuples::bigint AS rows
FROM pg_class c
WHERE c.relname IN ('ostsr_daily', 'tutsr_daily', 'gstsr_daily',
                    'ostsr_daily_old', 'tutsr_daily_old', 'gstsr_daily_old');

-- Once the SKO, stock and trade unit index pages have been checked:
-- DROP TABLE ostsr_daily_old, tutsr_daily_old, gstsr_daily_old;
