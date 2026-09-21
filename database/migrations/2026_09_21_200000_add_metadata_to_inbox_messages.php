<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('action_url');
            $table->index(['recipient_user_id', 'category', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropIndex(['recipient_user_id', 'category', 'read_at']);
            $table->dropColumn('metadata');
        });
    }
};
