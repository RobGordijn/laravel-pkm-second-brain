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
        Schema::table('digests', function (Blueprint $table) {
            $table->json('project_suggestions')->nullable()->after('stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digests', function (Blueprint $table) {
            $table->dropColumn('project_suggestions');
        });
    }
};
