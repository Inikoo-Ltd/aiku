<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    /**
     * Changes Aiku thinks are worth making to an advertising account, for a person to approve.
     *
     * Three columns carry the weight, and they are deliberately separate:
     *
     * `payload` is the arguments of the action that would run. Nothing else decides what happens on
     * approval, so a proposal can never do more than the form a person could have filled in.
     *
     * `evidence` is the figures the proposal was built from, and the window they came from. It is what
     * the card shows, and it is re-read before applying: a proposal raised on Monday and approved on
     * Friday has to still be true on Friday.
     *
     * `rationale` is the only part a language model writes. It explains, it never decides, and no
     * number in it is trusted: the card renders the evidence, not the prose.
     */
    public function up(): void
    {
        Schema::create('traffic_source_ad_proposals', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();

            $table->unsignedInteger('traffic_source_id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->cascadeOnDelete();
            $table->unsignedInteger('traffic_source_campaign_id')->nullable();
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->cascadeOnDelete();

            $table->string('type', 64);
            $table->string('state', 32)->default('open');

            /* Identifies the suggestion rather than the row, so tonight's run recognises what it
               proposed last night. A dismissal then sticks forever, which is the whole reason anyone
               keeps opening this page after the second week. */
            $table->string('fingerprint', 64);

            $table->jsonb('payload');
            $table->jsonb('evidence');
            $table->text('rationale')->nullable();

            /* Spend at risk, or spend about to be committed, in the AD ACCOUNT's currency, with the
               code recorded in `evidence`. Not converted: this is a sort key for one shop's queue, and
               a conversion here would introduce a rate and a rounding error for no one's benefit. */
            $table->decimal('amount', 16, 2)->default(0);

            $table->unsignedSmallInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestampsTz();

            $table->unique(['traffic_source_id', 'fingerprint']);
            $table->index(['shop_id', 'state', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_ad_proposals');
    }
};
