<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('source_title')->nullable()->after('url');
            $table->text('source_text')->nullable()->after('source_title');
            $table->timestamp('source_fetched_at')->nullable()->after('source_text');
        });

        Schema::table('item_links', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('similarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['source_title', 'source_text', 'source_fetched_at']);
        });

        Schema::table('item_links', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
