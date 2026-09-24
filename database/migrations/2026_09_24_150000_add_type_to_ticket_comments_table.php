<?php

use App\Models\Helpers\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('type')->default('comment')->index();
        });

        DB::table('tickets')->whereNotNull(DB::raw("data->'deploy_comment'"))->orderBy('id')->each(function (object $ticket) {
            $data          = json_decode($ticket->data, true) ?? [];
            $deployComment = $data['deploy_comment'] ?? null;
            unset($data['deploy_comment']);

            if (is_array($deployComment) && trim((string) ($deployComment['body'] ?? '')) !== '') {
                DB::table('ticket_comments')->insert([
                    'ticket_id'   => $ticket->id,
                    'author_type' => ($deployComment['user_id'] ?? null) ? 'User' : null,
                    'author_id'   => $deployComment['user_id'] ?? null,
                    'body'        => $deployComment['body'],
                    'type'        => 'waiting_for_deployment',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::table('tickets')->where('id', $ticket->id)->update(['data' => json_encode($data)]);
        });

        Ticket::refreshSearchVectors();
    }

    public function down(): void
    {
        DB::table('ticket_comments')->where('type', 'waiting_for_deployment')->orderBy('id')->each(function (object $comment) {
            $ticket = DB::table('tickets')->where('id', $comment->ticket_id)->first();
            $data   = json_decode($ticket->data ?? 'null', true) ?? [];

            $data['deploy_comment'] = ['body' => $comment->body, 'user_id' => $comment->author_id];
            DB::table('tickets')->where('id', $comment->ticket_id)->update(['data' => json_encode($data)]);
        });
        DB::table('ticket_comments')->where('type', 'waiting_for_deployment')->delete();

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
