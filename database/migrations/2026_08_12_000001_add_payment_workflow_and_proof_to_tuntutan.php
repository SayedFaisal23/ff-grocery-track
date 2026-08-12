<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add explicit payment workflow configuration and private company-transfer
     * proof tracking without changing the behavior of existing claims.
     */
    public function up(): void
    {
        Schema::table('tuntutan_presets', function (Blueprint $table) {
            $table->string('payment_workflow')->nullable()->after('name');
        });

        Schema::table('tuntutan', function (Blueprint $table) {
            $table->string('payment_workflow')->default('legacy')->after('other_payment_method');
            $table->string('payment_proof_attachment')->nullable()->after('purchase_attachment');

            $table->foreignId('payment_proof_attachment_viewed_by')
                ->nullable()
                ->after('payment_proof_attachment')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('payment_proof_attachment_viewed_at')
                ->nullable()
                ->after('payment_proof_attachment_viewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('tuntutan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_proof_attachment_viewed_by');
            $table->dropColumn([
                'payment_proof_attachment_viewed_at',
                'payment_proof_attachment',
                'payment_workflow',
            ]);
        });

        Schema::table('tuntutan_presets', function (Blueprint $table) {
            $table->dropColumn('payment_workflow');
        });
    }
};
