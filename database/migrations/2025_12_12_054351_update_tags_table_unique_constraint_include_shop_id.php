<?php

use App\Models\Helpers\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique('unique_scope_name');
        });

        // Create unique index that handles nullable shop_id properly
        // This allows:
        // - Same name+scope for different shops
        // - Same name+scope at group level (shop_id = NULL) - only one instance
        DB::statement('CREATE UNIQUE INDEX unique_scope_shop_name ON tags (scope, COALESCE(shop_id, 0), name)');

        // Regenerate slugs for existing tags based on hierarchy
        $tags = Tag::with(['shop', 'organisation'])->get();

        foreach ($tags as $tag) {
            $newSlug = null;

            // Shop level: tag-slug + shop-slug
            if ($tag->shop_id && $tag->shop) {
                $baseSlug = Str::slug($tag->name);
                $newSlug = $baseSlug.'-'.$tag->shop->slug;
            }
            // Organisation level: tag-slug + org-slug
            elseif ($tag->organisation_id && $tag->organisation) {
                $baseSlug = Str::slug($tag->name);
                $newSlug = $baseSlug.'-'.$tag->organisation->slug;
            }
            // Group level: tag-slug only
            else {
                $newSlug = Str::slug($tag->name);
            }

            if ($newSlug && $newSlug !== $tag->slug) {
                // Check if slug already exists, if so, add unique suffix
                $originalSlug = $newSlug;
                $counter = 1;
                while (Tag::where('slug', $newSlug)->where('id', '!=', $tag->id)->exists()) {
                    $newSlug = $originalSlug.'-'.$counter;
                    $counter++;
                }

                $tag->slug = $newSlug;
                $tag->save();
            }
        }
    }


    public function down(): void
    {
        // Drop the new unique index
        DB::statement('DROP INDEX IF EXISTS unique_scope_shop_name');

        Schema::table('tags', function (Blueprint $table) {
            // Restore the old unique constraint
            $table->unique(['scope', 'name'], 'unique_scope_name');
        });

        // Note: We don't regenerate old slugs as they may have changed
        // Manual intervention may be needed if rolling back
    }
};
