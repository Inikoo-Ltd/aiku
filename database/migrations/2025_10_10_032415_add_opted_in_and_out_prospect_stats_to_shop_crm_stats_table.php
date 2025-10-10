<?php

use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Enums\Miscellaneous\GenderEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            // Opt In
            $table->unsignedInteger('number_opt_in_prospects')->default(0);
            foreach (ProspectStateEnum::cases() as $prospectState) {
                $table->unsignedInteger("number_opt_in_prospects_state_{$prospectState->snake()}")->default(0);
            }
            foreach (GenderEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_opt_in_prospects_gender_'.$case->snake())->default(0);
            }
            foreach (ProspectContactedStateEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_in_prospects_contacted_state_{$case->snake()}")->default(0);
            }
            foreach (ProspectFailStatusEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_in_prospects_fail_status_{$case->snake()}")->default(0);
            }
            foreach (ProspectSuccessStatusEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_in_prospects_success_status_{$case->snake()}")->default(0);
            }
            $table->unsignedInteger("number_opt_in_prospects_dont_contact_me")->default(0);

            // Opt Out
            $table->unsignedInteger('number_opt_out_prospects')->default(0);
            foreach (ProspectStateEnum::cases() as $prospectState) {
                $table->unsignedInteger("number_opt_out_prospects_state_{$prospectState->snake()}")->default(0);
            }
            foreach (GenderEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_opt_out_prospects_gender_'.$case->snake())->default(0);
            }
            foreach (ProspectContactedStateEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_out_prospects_contacted_state_{$case->snake()}")->default(0);
            }
            foreach (ProspectFailStatusEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_out_prospects_fail_status_{$case->snake()}")->default(0);
            }
            foreach (ProspectSuccessStatusEnum::cases() as $case) {
                $table->unsignedInteger("number_opt_out_prospects_success_status_{$case->snake()}")->default(0);
            }
            $table->unsignedInteger("number_opt_out_prospects_dont_contact_me")->default(0);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            // Opt In
            $table->dropColumn('number_opt_in_prospects');
            foreach (ProspectStateEnum::cases() as $prospectState) {
                $table->dropColumn("number_opt_in_prospects_state_{$prospectState->snake()}");
            }
            foreach (GenderEnum::cases() as $case) {
                $table->dropColumn('number_opt_in_prospects_gender_'.$case->snake());
            }
            foreach (ProspectContactedStateEnum::cases() as $case) {
                $table->dropColumn("number_opt_in_prospects_contacted_state_{$case->snake()}");
            }
            foreach (ProspectFailStatusEnum::cases() as $case) {
                $table->dropColumn("number_opt_in_prospects_fail_status_{$case->snake()}");
            }
            foreach (ProspectSuccessStatusEnum::cases() as $case) {
                $table->dropColumn("number_opt_in_prospects_success_status_{$case->snake()}");
            }
            $table->dropColumn("number_opt_in_prospects_dont_contact_me");

            // Opt Out
            $table->dropColumn('number_opt_out_prospects');
            foreach (ProspectStateEnum::cases() as $prospectState) {
                $table->dropColumn("number_opt_out_prospects_state_{$prospectState->snake()}");
            }
            foreach (GenderEnum::cases() as $case) {
                $table->dropColumn('number_opt_out_prospects_gender_'.$case->snake());
            }
            foreach (ProspectContactedStateEnum::cases() as $case) {
                $table->dropColumn("number_opt_out_prospects_contacted_state_{$case->snake()}");
            }
            foreach (ProspectFailStatusEnum::cases() as $case) {
                $table->dropColumn("number_opt_out_prospects_fail_status_{$case->snake()}");
            }
            foreach (ProspectSuccessStatusEnum::cases() as $case) {
                $table->dropColumn("number_opt_out_prospects_success_status_{$case->snake()}");
            }
            $table->dropColumn("number_opt_out_prospects_dont_contact_me");
        });
    }
};
