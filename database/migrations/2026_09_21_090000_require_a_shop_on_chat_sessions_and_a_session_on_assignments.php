<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Everything that belongs to a conversation goes when the conversation goes.
     *
     * Each of these keys was ON DELETE SET NULL, so deleting a chat session cut its
     * messages, events and assignments loose rather than taking them with it, leaving
     * rows that belong to nothing: on production 132 messages, 163 events and 23
     * assignments, and an agent still shown as holding a chat that no longer exists.
     * Cleaning them is not enough while the rule that made them stands.
     *
     * A session also needs the shop it belongs to. Without one nothing can be said about
     * who may work it, which is how an assignment ends up released for want of a shop.
     *
     * @var array<int, array{table: string, column: string, parent: string, key: string}>
     */
    private array $children = [
        ['table' => 'chat_messages', 'column' => 'chat_session_id', 'parent' => 'chat_sessions', 'key' => 'chat_messages_chat_session_id_foreign'],
        ['table' => 'chat_events', 'column' => 'chat_session_id', 'parent' => 'chat_sessions', 'key' => 'chat_events_chat_session_id_foreign'],
        ['table' => 'chat_assignments', 'column' => 'chat_session_id', 'parent' => 'chat_sessions', 'key' => 'chat_assignments_chat_session_id_foreign'],
        ['table' => 'meta_chat_messages', 'column' => 'meta_chat_session_id', 'parent' => 'meta_chat_sessions', 'key' => 'meta_chat_messages_meta_chat_session_id_foreign'],
        ['table' => 'meta_chat_events', 'column' => 'meta_chat_session_id', 'parent' => 'meta_chat_sessions', 'key' => 'meta_chat_events_meta_chat_session_id_foreign'],
        ['table' => 'meta_chat_assignments', 'column' => 'meta_chat_session_id', 'parent' => 'meta_chat_sessions', 'key' => 'meta_chat_assignments_meta_chat_session_id_foreign'],
    ];

    public function up(): void
    {
        // Sessions first: while the keys still set null on delete, removing a session
        // orphans its children, so cleaning beforehand would miss the ones this creates.
        DB::table('chat_sessions')->whereNull('shop_id')->delete();

        foreach ($this->children as $child) {
            DB::table($child['table'])->whereNull($child['column'])->delete();

            DB::statement("ALTER TABLE {$child['table']} DROP CONSTRAINT {$child['key']}");
            DB::statement("ALTER TABLE {$child['table']} ALTER COLUMN {$child['column']} SET NOT NULL");
            DB::statement("ALTER TABLE {$child['table']} ADD CONSTRAINT {$child['key']} FOREIGN KEY ({$child['column']}) REFERENCES {$child['parent']}(id) ON DELETE CASCADE");
        }

        DB::statement('ALTER TABLE chat_sessions ALTER COLUMN shop_id SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE chat_sessions ALTER COLUMN shop_id DROP NOT NULL');

        foreach ($this->children as $child) {
            DB::statement("ALTER TABLE {$child['table']} DROP CONSTRAINT {$child['key']}");
            DB::statement("ALTER TABLE {$child['table']} ALTER COLUMN {$child['column']} DROP NOT NULL");
            DB::statement("ALTER TABLE {$child['table']} ADD CONSTRAINT {$child['key']} FOREIGN KEY ({$child['column']}) REFERENCES {$child['parent']}(id) ON DELETE SET NULL");
        }
    }
};
