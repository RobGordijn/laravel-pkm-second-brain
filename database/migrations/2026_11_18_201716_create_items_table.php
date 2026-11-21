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
        Schema::ensureVectorExtensionExists();

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->text('raw');
            $table->string('url', 2048)->nullable();
            $table->string('type')->nullable()->index();
            $table->float('type_confidence')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('clarification_question')->nullable();
            $table->text('clarification_answer')->nullable();
            $table->text('error')->nullable();
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE items ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(summary, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(body, '') || ' ' || raw || ' ' || coalesce(clarification_answer, '')), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX items_search_vector_index ON items USING GIN (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
