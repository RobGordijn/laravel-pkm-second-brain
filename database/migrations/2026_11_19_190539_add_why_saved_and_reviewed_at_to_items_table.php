<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('why_saved')->nullable()->after('raw');
            $table->timestamp('reviewed_at')->nullable()->index()->after('archived_at');
        });

        $this->replaceSearchVector(withWhySaved: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceSearchVector(withWhySaved: false);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['why_saved', 'reviewed_at']);
        });
    }

    /**
     * A generated column cannot change its expression, so it is dropped and added again.
     */
    private function replaceSearchVector(bool $withWhySaved): void
    {
        $summaryExpression = $withWhySaved ? "coalesce(summary, '') || ' ' || coalesce(why_saved, '')" : "coalesce(summary, '')";

        DB::statement('DROP INDEX IF EXISTS items_search_vector_index');
        DB::statement('ALTER TABLE items DROP COLUMN search_vector');
        DB::statement(<<<SQL
            ALTER TABLE items ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(key_insight, '')), 'A') ||
                setweight(to_tsvector('simple', {$summaryExpression}), 'B') ||
                setweight(to_tsvector('simple', coalesce(body, '') || ' ' || raw || ' ' || coalesce(clarification_answer, '')), 'C')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX items_search_vector_index ON items USING GIN (search_vector)');
    }
};
