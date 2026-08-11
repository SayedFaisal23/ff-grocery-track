<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add private supporting-document storage and audit fields without
     * changing historic public attachment records.
     */
    public function up(): void
    {
        Schema::table('tuntutan', function (Blueprint $table) {
            $table->string('purchase_attachment')->nullable()->after('attachment');

            $table->foreignId('purchase_attachment_viewed_by')
                ->nullable()
                ->after('purchase_attachment')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('purchase_attachment_viewed_at')
                ->nullable()
                ->after('purchase_attachment_viewed_by');

            $table->foreignId('attachment_viewed_by')
                ->nullable()
                ->after('receipt_viewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('attachment_viewed_at')
                ->nullable()
                ->after('attachment_viewed_by');

            $table->foreignId('latest_attachment_downloaded_by')
                ->nullable()
                ->after('attachment_viewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('latest_attachment_downloaded_at')
                ->nullable()
                ->after('latest_attachment_downloaded_by');

            $table->foreignId('claim_details_viewed_by')
                ->nullable()
                ->after('latest_attachment_downloaded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('claim_details_viewed_at')
                ->nullable()
                ->after('claim_details_viewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('tuntutan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_attachment_viewed_by');
            $table->dropColumn('purchase_attachment_viewed_at');
            $table->dropConstrainedForeignId('attachment_viewed_by');
            $table->dropColumn('attachment_viewed_at');
            $table->dropConstrainedForeignId('latest_attachment_downloaded_by');
            $table->dropColumn('latest_attachment_downloaded_at');
            $table->dropConstrainedForeignId('claim_details_viewed_by');
            $table->dropColumn('claim_details_viewed_at');
            $table->dropColumn('purchase_attachment');
        });
    }
};
