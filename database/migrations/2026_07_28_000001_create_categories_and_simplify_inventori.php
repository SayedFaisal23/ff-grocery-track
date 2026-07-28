<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->timestamps();
        });

        Schema::table('inventori', function (Blueprint $table) {
            $table->foreignId('kategori_id')
                ->nullable()
                ->after('id')
                ->constrained('categories')
                ->restrictOnDelete();
        });

        $categoryNames = DB::table('inventori')
            ->whereNotNull('kategori')
            ->distinct()
            ->pluck('kategori')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique();

        foreach ($categoryNames as $name) {
            $categoryId = DB::table('categories')->insertGetId([
                'nama' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventori')
                ->where('kategori', $name)
                ->update(['kategori_id' => $categoryId]);
        }

        Schema::table('inventori', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'jenama', 'jumlah_keseluruhan']);
            $table->boolean('jejak_luput')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventori', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('nama_item');
            $table->string('jenama')->nullable()->after('kategori');
            $table->integer('jumlah_keseluruhan')->default(0)->after('capacity');
            $table->boolean('jejak_luput')->default(true)->change();
        });

        DB::table('inventori')
            ->join('categories', 'inventori.kategori_id', '=', 'categories.id')
            ->select('inventori.id', 'categories.nama', 'inventori.jumlah_belum_dibuka')
            ->orderBy('inventori.id')
            ->each(function ($item) {
                DB::table('inventori')
                    ->where('id', $item->id)
                    ->update([
                        'kategori' => $item->nama,
                        'jumlah_keseluruhan' => $item->jumlah_belum_dibuka,
                    ]);
            });

        Schema::table('inventori', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kategori_id');
        });

        Schema::dropIfExists('categories');
    }
};
