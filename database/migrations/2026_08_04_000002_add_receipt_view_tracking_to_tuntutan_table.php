<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuntutan', function (Blueprint $table) {
            $table->foreignId('receipt_viewed_by')
                ->nullable()
                ->after('attachment')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('receipt_viewed_at')
                ->nullable()
                ->after('receipt_viewed_by');
        });

        DB::table('tuntutan')
            ->whereIn('tag', ['Pantry', 'General'])
            ->whereNotNull('attachment')
            ->update(['receipt_viewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tuntutan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_viewed_by');
            $table->dropColumn('receipt_viewed_at');
        });
    }
};
