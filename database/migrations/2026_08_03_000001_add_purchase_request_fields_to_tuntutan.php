<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuntutan_presets', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index(['type', 'sort_order']);
        });

        Schema::table('tuntutan', function (Blueprint $table) {
            $table->string('requestor_name')->nullable()->after('user_id');
            $table->date('request_date')->nullable()->after('tarikh_beli');
            $table->string('item_specification')->nullable()->after('nama_item');
            $table->text('purchase_purpose')->nullable()->after('item_specification');
            $table->string('invoice_no')->nullable()->after('purchase_purpose');
            $table->string('purchase_platform')->nullable()->after('invoice_no');
            $table->decimal('total_item_amount', 10, 2)->nullable()->after('nilai_tuntutan');
            $table->string('payment_method')->nullable()->after('total_item_amount');
            $table->boolean('invoice_sent_to_account')->nullable()->after('payment_method');
            $table->date('date_receive')->nullable()->after('invoice_sent_to_account');
            $table->string('approval_result')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('approval_result')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        $userNames = DB::table('users')->pluck('name', 'id');

        DB::table('tuntutan')->orderBy('id')->chunkById(100, function ($claims) use ($userNames): void {
            foreach ($claims as $claim) {
                $oldStatus = $claim->status;

                DB::table('tuntutan')->where('id', $claim->id)->update([
                    'tag' => match ($claim->tag) {
                        'Stok' => 'Pantry',
                        'Food' => 'General',
                        default => $claim->tag,
                    },
                    'status' => match ($oldStatus) {
                        'Selesai', 'Ditolak' => 'Completed',
                        default => 'Pending',
                    },
                    'approval_result' => match ($oldStatus) {
                        'Selesai' => 'Approved',
                        'Ditolak' => 'Rejected',
                        default => null,
                    },
                    'requestor_name' => $userNames->get($claim->user_id),
                    'request_date' => $claim->tarikh_beli,
                    'item_specification' => $claim->nama_item,
                    'total_item_amount' => $claim->nilai_tuntutan,
                ]);
            }
        });

        Schema::table('tuntutan', function (Blueprint $table) {
            $table->string('status')->default('Pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('tuntutan')->where('tag', 'Pantry')->update(['tag' => 'Stok']);
        DB::table('tuntutan')->where('status', 'Pending')->update(['status' => 'Dalam Proses']);
        DB::table('tuntutan')->where('approval_result', 'Approved')->update(['status' => 'Selesai']);
        DB::table('tuntutan')->where('approval_result', 'Rejected')->update(['status' => 'Ditolak']);

        Schema::table('tuntutan', function (Blueprint $table) {
            $table->string('status')->default('Dalam Proses')->change();
        });

        Schema::table('tuntutan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'requestor_name',
                'request_date',
                'item_specification',
                'purchase_purpose',
                'invoice_no',
                'purchase_platform',
                'total_item_amount',
                'payment_method',
                'invoice_sent_to_account',
                'date_receive',
                'approval_result',
                'reviewed_at',
            ]);
        });

        Schema::dropIfExists('tuntutan_presets');
    }
};
