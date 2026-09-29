<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('has_qa_verdict')->nullable()->index();
        });

        /* The verdict comments already posted carry it only in their body, which UpdateTicket
           wrote as the label followed by the note ("QA failed: the total is still wrong").
           Matching the English labels recovers them; a comment written while the app was in
           another locale keeps a null verdict and simply renders as an ordinary comment. */
        foreach (['QA passed' => 'passed', 'QA failed' => 'failed', 'QA skipped' => 'skipped'] as $label => $verdict) {
            DB::table('ticket_comments')
                ->whereNull('has_qa_verdict')
                ->where(function ($query) use ($label) {
                    $query->where('body', $label)->orWhere('body', 'like', $label.': %');
                })
                ->update(['has_qa_verdict' => $verdict]);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('has_qa_verdict');
        });
    }
};
