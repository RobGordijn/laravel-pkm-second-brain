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
            $table->text('key_insight')->nullable()->after('summary');
            $table->unsignedInteger('use_count')->default(0)->after('archived_at');
            $table->timestamp('last_used_at')->nullable()->after('use_count');
        });

        $this->replaceSearchVector(withKeyInsight: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceSearchVector(withKeyInsight: false);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['key_insight', 'use_count', 'last_used_at']);
        });
    }

    /**
     * A generated column cannot change its expression, so it is dropped and added again.
     */
    private function replaceSearchVector(bool $withKeyInsight): void
    {
        $titleExpression = $withKeyInsight ? "coalesce(title, '') || ' ' || coalesce(key_insight, '')" : "coalesce(title, '')";

        DB::statement('DROP INDEX IF EXISTS items_search_vector_index');
        DB::statement('ALTER TABLE items DROP COLUMN search_vector');
        DB::statement(<<<SQL
            ALTER TABLE items ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', {$titleExpression}), 'A') ||
                setweight(to_tsvector('simple', coalesce(summary, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(body, '') || ' ' || raw || ' ' || coalesce(clarification_answer, '')), 'C')
            ) STORED
        SQL);
        DB::statement('CREATE INDEX items_search_vector_index ON items USING GIN (search_vector)');
    }
};
