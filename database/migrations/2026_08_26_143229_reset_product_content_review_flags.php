<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * The review flags are what raises the shop's "master text changed" badge from here on.
     * They were never written before this, so the false and null rows left behind are noise
     * from an earlier import, not work anybody queued. Clearing them opens the badge at zero;
     * a badge that opens at twelve thousand is a badge nobody ever opens.
     */
    public function up(): void
    {
        $flags = [
            'is_name_reviewed',
            'is_description_title_reviewed',
            'is_description_reviewed',
            'is_description_extra_reviewed',
        ];

        /**
         * The flags were never written before this, so in practice every row is null and this
         * rewrites the whole table - a couple of minutes on a dev copy. The predicate is kept
         * so a re-run after the first deploy is cheap, not because the first one will be.
         */
        DB::table('products')->where(function ($query) use ($flags) {
            foreach ($flags as $flag) {
                $query->orWhere($flag, '!=', true)->orWhereNull($flag);
            }
        })->update([
            'is_name_reviewed'              => true,
            'is_description_title_reviewed' => true,
            'is_description_reviewed'       => true,
            'is_description_extra_reviewed' => true,
        ]);
    }

    public function down(): void
    {
        //
    }
};
