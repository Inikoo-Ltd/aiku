<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The concept now comes straight after the brief, before the specs, terms, pack content and formulation.
 * A development that was working on its specs goes back to the concept; the answers already given stay.
 */
return new class () extends Migration {
    private const array SPECS_STAGES = ['product_specs', 'commercial_terms', 'pack_content', 'formulation'];

    public function up(): void
    {
        $this->moveExistingDevelopments();
    }

    /**
     * Safe to run again: only developments whose concept is still after the specs are moved.
     */
    private function moveExistingDevelopments(): void
    {
        $now = now();

        foreach (DB::table('product_developments')->get(['id', 'current_position']) as $development) {
            $stages  = DB::table('product_development_stages')->where('product_development_id', $development->id);
            $concept = (clone $stages)->where('stage', 'concept')->where('position', 3)->first();
            if (!$concept) {
                continue;
            }

            $specsStages = (clone $stages)->whereIn('stage', self::SPECS_STAGES);
            $specsDueAt  = (clone $specsStages)->min('due_at');

            DB::transaction(function () use ($development, $concept, $specsStages, $specsDueAt, $now) {
                DB::table('product_development_stages')->where('id', $concept->id)->update([
                    'position'   => 2,
                    'due_at'     => $specsDueAt ?? $concept->due_at,
                    'updated_at' => $now,
                ]);

                (clone $specsStages)->update([
                    'position'   => 3,
                    'due_at'     => $concept->due_at,
                    'updated_at' => $now,
                ]);

                $position = (int) $development->current_position;
                if ($position === 2) {
                    DB::table('product_development_stages')->where('id', $concept->id)->where('state', 'pending')->update([
                        'state'      => 'in_progress',
                        'started_at' => $now,
                    ]);

                    (clone $specsStages)->where('state', 'in_progress')->update([
                        'state'      => 'pending',
                        'started_at' => null,
                    ]);
                } elseif ($position === 3) {
                    DB::table('product_developments')->where('id', $development->id)->update(['current_position' => 2]);
                }
            });
        }
    }

    public function down(): void
    {
    }
};
