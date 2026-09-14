<?php

/*
 * Author: YudhistiraA <aryarajasa0@gmail.com>
 * Copyright (c) 2026, YudhistiraA
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private array $strategies = [
        'desktop',
        'mobile',
    ];

    private array $scores = [
        'performance',
        'accessibility',
        'best_practices',
        'seo',
    ];

    public function up(): void
    {
        foreach ($this->strategies as $strategy) {
            foreach ($this->scores as $score) {
                DB::statement("ALTER TABLE webpage_time_series_records ADD COLUMN IF NOT EXISTS pagespeed_{$strategy}_$score smallint");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->strategies as $strategy) {
            foreach ($this->scores as $score) {
                DB::statement("ALTER TABLE webpage_time_series_records DROP COLUMN IF EXISTS pagespeed_{$strategy}_$score");
            }
        }
    }
};
