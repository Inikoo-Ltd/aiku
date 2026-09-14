<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * An order whose pay status was never computed belonged to neither the paid nor the unpaid half
     * of the backlog, so it was submitted into a queue nobody could see (HELP-3116). The two halves
     * can only be exhaustive if the column is never null, so the database enforces it rather than
     * every reader having to remember a third case.
     *
     * Raw statements rather than Blueprint::change(): change() restates the column type, which
     * rewrites all 1.3M rows under an exclusive lock. SET DEFAULT is instant and SET NOT NULL costs
     * one scan to verify, both on a table whose nulls have just been cleared.
     */
    public function up(): void
    {
        /**
         * Every null row today has taken no money at all, so unpaid is what they are. Deriving a
         * status from the amounts instead would have to guess between paid, overpaid, partially
         * paid and refunded, and guessing wrong towards paid drops an order out of the chase queue
         * for good. Unpaid is the safe way to be wrong: UpdateOrderPaymentsStatus corrects it the
         * next time the order is touched, and until then it is merely chased.
         */
        foreach (['pay_status', 'pay_detailed_status'] as $column) {
            DB::table('orders')->whereNull($column)->update([$column => 'unpaid']);

            DB::statement("alter table orders alter column $column set default 'unpaid'");
        }

        /** Both columns in one statement: each SET NOT NULL costs a full scan of the table, and
         * two separate ones take that lock twice over. */
        DB::statement('alter table orders alter column pay_status set not null, alter column pay_detailed_status set not null');
    }

    public function down(): void
    {
        foreach (['pay_status', 'pay_detailed_status'] as $column) {
            DB::statement("alter table orders alter column $column drop not null");
            DB::statement("alter table orders alter column $column drop default");
        }
    }
};
