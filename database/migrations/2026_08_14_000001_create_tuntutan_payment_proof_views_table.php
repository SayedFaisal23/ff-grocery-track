<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track each user's review of a company payment proof.
     */
    public function up(): void
    {
        Schema::create('tuntutan_payment_proof_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuntutan_id')
                ->constrained('tuntutan')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['tuntutan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuntutan_payment_proof_views');
    }
};
