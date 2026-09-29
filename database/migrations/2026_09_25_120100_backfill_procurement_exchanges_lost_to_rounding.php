<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * numeric(16,4) kept four decimals, so a rate that belongs below this lost more than 0.1% of itself.
     */
    private const float DAMAGED_RATE_BELOW = 0.05;

    /**
     * Below this the four decimals kept at most one significant digit (IDR→GBP 0.0000417 became 0.0000 or 0.0001).
     */
    private const float UNREADABLE_RATE_BELOW = 0.001;

    /**
     * A stored rate within this of the derived one is that rate rounded to four decimals. Further away it is
     * Aurora's own purchase order rate, which differs from currency_exchanges by design and is kept.
     */
    private const float ROUNDING = 0.00005;

    public function up(): void
    {
        $this->createExchangeFunctions();

        $this->restoreDamagedRates('purchase_order_transactions', datedBy: 'purchase_orders', datedByKey: 'purchase_order_id', withNetAmounts: true);
        $this->restoreDamagedRates('purchase_orders', datedBy: 'purchase_orders', datedByKey: 'id');
        $this->restoreDamagedRates('stock_delivery_items', datedBy: 'stock_deliveries', datedByKey: 'stock_delivery_id', withNetAmounts: true);
        $this->restoreDamagedRates('stock_deliveries', datedBy: 'stock_deliveries', datedByKey: 'id');
    }

    public function down(): void
    {
    }

    private function restoreDamagedRates(string $table, string $datedBy, string $datedByKey, bool $withNetAmounts = false): void
    {
        $damaged = self::DAMAGED_RATE_BELOW;

        $netAmounts = $withNetAmounts ? ",
            org_net_amount = coalesce(round(target_record.net_amount * rates.org_exchange, 2), target_record.org_net_amount),
            grp_net_amount = coalesce(round(target_record.net_amount * rates.grp_exchange, 2), target_record.grp_net_amount)" : '';

        DB::statement("
            update $table as target_record
            set org_exchange = coalesce(rates.org_exchange, target_record.org_exchange),
                grp_exchange = coalesce(rates.grp_exchange, target_record.grp_exchange)$netAmounts
            from (
                select target_record.id,
                    pg_temp.restored_exchange(
                        target_record.org_exchange,
                        pg_temp.historic_exchange(dated_by.currency_id, organisations.currency_id, (dated_by.date at time zone 'UTC')::date)
                    ) as org_exchange,
                    pg_temp.restored_exchange(
                        target_record.grp_exchange,
                        pg_temp.historic_exchange(dated_by.currency_id, groups.currency_id, (dated_by.date at time zone 'UTC')::date)
                    ) as grp_exchange
                from $table as target_record
                join $datedBy as dated_by on dated_by.id = target_record.$datedByKey
                join organisations on organisations.id = dated_by.organisation_id
                join groups on groups.id = dated_by.group_id
                where target_record.org_exchange < $damaged or target_record.grp_exchange < $damaged
            ) as rates
            where target_record.id = rates.id
        ");
    }

    /**
     * historic_exchange is the SQL twin of GetHistoricCurrencyExchange: both currencies against the pivot on that day,
     * the closest earlier day when a day is missing, the closest later one before the history starts.
     * restored_exchange returns the derived rate when it replaces the stored one, null when the stored one stays.
     */
    private function createExchangeFunctions(): void
    {
        $pivotCurrencyId = (int) DB::table('currencies')->where('code', config('app.currency_exchange.pivot'))->value('id');
        $damaged         = self::DAMAGED_RATE_BELOW;
        $unreadable      = self::UNREADABLE_RATE_BELOW;
        $rounding        = self::ROUNDING;

        DB::statement("
            create or replace function pg_temp.exchange_against_pivot(for_currency_id integer, on_date date) returns numeric
            language sql stable as \$\$
                select case when for_currency_id = $pivotCurrencyId then 1 else coalesce(
                    (select exchange from currency_exchanges where currency_id = for_currency_id and date <= on_date order by date desc limit 1),
                    (select exchange from currency_exchanges where currency_id = for_currency_id and date > on_date order by date limit 1)
                ) end
            \$\$
        ");

        DB::statement("
            create or replace function pg_temp.historic_exchange(base_currency_id integer, target_currency_id integer, on_date date) returns numeric
            language sql stable as \$\$
                select case when base_currency_id = target_currency_id then 1
                    else pg_temp.exchange_against_pivot(target_currency_id, on_date) / nullif(pg_temp.exchange_against_pivot(base_currency_id, on_date), 0)
                end
            \$\$
        ");

        DB::statement("
            create or replace function pg_temp.restored_exchange(stored numeric, derived numeric) returns numeric
            language sql immutable as \$\$
                select case when stored < $damaged and derived < $damaged and (stored < $unreadable or abs(stored - derived) <= $rounding)
                    then derived
                end
            \$\$
        ");
    }
};
