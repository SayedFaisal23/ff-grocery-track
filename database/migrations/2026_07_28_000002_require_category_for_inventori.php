<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventori', function (Blueprint $table) {
            $table->unsignedBigInteger('kategori_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventori', function (Blueprint $table) {
            $table->unsignedBigInteger('kategori_id')->nullable()->change();
        });
    }
};
