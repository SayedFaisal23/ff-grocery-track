<?php

namespace App\Http\Controllers;

use App\Models\Tuntutan;
use App\Models\LogAktiviti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TuntutanController extends Controller
{
    /**
     * Paparkan senarai tuntutan berkumpulan mengikut minggu.
     */
    public function index()
    {
        $user = Auth::user();
        $query = Tuntutan::query();

        // Stocker hanya boleh melihat tuntutan sendiri.
        // Superadmin & Tracker boleh melihat semua tuntutan.
        if ($user->hasRole('Stocker')) {
            $query->where('user_id', $user->id);
        }

        // Dapatkan semua tuntutan dan urutkan mengikut tarikh beli terkini
        $claims = $query->orderBy('tarikh_beli', 'desc')->get();

        // Kumpulkan mengikut minggu_tuntutan
        $claimsGrouped = $claims->groupBy('minggu_tuntutan');

        return view('tuntutan.index', compact('claimsGrouped'));
    }

    /**
     * Tunjukkan borang tambah tuntutan.
     */
    public function create()
    {
        // Hanya Stocker sahaja dibenarkan membuat tuntutan
        if (!Auth::user()->hasRole('Stocker')) {
            abort(403, 'Hanya Stocker sahaja dibenarkan membuat tuntutan.');
        }

        return view('tuntutan.create');
    }

    /**
     * Paparkan lampiran tuntutan melalui aplikasi, tanpa bergantung pada
     * pelayan web untuk membenarkan akses kepada symbolic link /storage.
     */
    public function showAttachment(Tuntutan $tuntutan)
    {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin') && $tuntutan->user_id !== $user->id) {
            abort(403);
        }

        $attachmentPath = $tuntutan->attachment;

        if (
            ! is_string($attachmentPath)
            || ! str_starts_with($attachmentPath, 'attachments/')
            || str_contains($attachmentPath, '..')
            || ! Storage::disk('public')->exists($attachmentPath)
        ) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachmentPath,
            basename($attachmentPath),
            ['X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }

    /**
     * Simpan tuntutan baharu.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasRole('Stocker')) {
            abort(403);
        }

        $tag = $request->input('tag');
        if (!in_array($tag, ['Stok', 'Lunch', 'General', 'Food'])) {
            return back()->withInput()->withErrors(['tag' => 'Jenis tuntutan tidak sah.']);
        }

        if ($tag === 'Stok' || $tag === 'General' || $tag === 'Food') {
            $validated = $request->validate([
                'nama_item'      => 'required|string|max:255',
                'tag'            => 'required|in:Stok,General,Food',
                'nilai_tuntutan' => 'required|numeric|min:0.01',
                'tarikh_beli'    => 'required|date|before_or_equal:today',
                'attachment'     => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            ], [
                'nama_item.required'      => 'Sila masukkan nama item yang dibeli.',
                'nilai_tuntutan.required' => 'Sila masukkan nilai tuntutan.',
                'nilai_tuntutan.numeric'  => 'Nilai tuntutan mestilah dalam bentuk nombor.',
                'nilai_tuntutan.min'      => 'Nilai tuntutan mestilah lebih daripada RM0.00.',
                'tarikh_beli.required'    => 'Sila masukkan tarikh pembelian.',
                'tarikh_beli.before_or_equal' => 'Tarikh pembelian tidak boleh pada masa hadapan.',
                'attachment.mimes'        => 'Dokumen mestilah berformat JPEG, JPG, PNG atau PDF.',
                'attachment.max'          => 'Saiz fail dokumen tidak boleh melebihi 5MB.',
            ]);

            // Hitung minggu tuntutan (Format: YYYY-Www)
            $date = Carbon::parse($request->tarikh_beli);
            // Dapatkan tahun ISO-8601 dan nombor minggu
            $year = $date->format('o'); 
            $week = sprintf('%02d', $date->weekOfYear);
            $validated['minggu_tuntutan'] = "{$year}-W{$week}";
            $validated['user_id'] = Auth::id();
            $validated['status'] = 'Dalam Proses'; // Default status

            if ($request->hasFile('attachment')) {
                $validated['attachment'] = $request->file('attachment')->store('attachments', 'public');
            }

            $claim = Tuntutan::create($validated);

            // Log Aktiviti
            LogAktiviti::create([
                'user_id'  => Auth::id(),
                'aktiviti' => "Membuat tuntutan baharu bagi pembelian: {$claim->nama_item} [{$claim->tag}] bernilai RM{$claim->nilai_tuntutan}.",
                'item_id'  => null,
                'data_baru' => $claim->toArray(),
            ]);
        } else {
            // Lunch claim
            $request->validate([
                'week'            => 'required|regex:/^\d{4}-W\d{2}$/',
                'lunch_dates'     => 'required|array|size:7',
                'lunch_dates.*'   => 'required|date',
                'lunch_butirans'  => 'required|array|size:7',
                'lunch_pax'       => 'required|array|size:7',
                'lunch_pax.*'     => 'nullable|integer|min:0',
                'lunch_hargas'    => 'required|array|size:7',
                'lunch_hargas.*'  => 'nullable|numeric|min:0',
                'attachment'      => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            ], [
                'week.required'       => 'Sila pilih minggu.',
                'week.regex'          => 'Format minggu tidak sah.',
                'attachment.mimes'    => 'Dokumen mestilah berformat JPEG, JPG, PNG atau PDF.',
                'attachment.max'      => 'Saiz fail dokumen tidak boleh melebihi 5MB.',
            ]);

            $lunchDates = $request->input('lunch_dates');
            $lunchButirans = $request->input('lunch_butirans');
            $lunchPaxes = $request->input('lunch_pax');
            $lunchHargas = $request->input('lunch_hargas');
            $week = $request->input('week');

            $totalClaims = 0;
            $hasFutureDate = false;
            $missingButiran = false;
            $missingHarga = false;

            for ($i = 0; $i < 7; $i++) {
                $pax = intval($lunchPaxes[$i] ?? 0);
                if ($pax > 0) {
                    $totalClaims++;
                    
                    // Check if date is in the future
                    $date = Carbon::parse($lunchDates[$i]);
                    if ($date->isFuture()) {
                        $hasFutureDate = true;
                    }

                    // Check if butiran is missing
                    if (empty(trim($lunchButirans[$i] ?? ''))) {
                        $missingButiran = true;
                    }

                    // Check if harga is missing or <= 0
                    $harga = floatval($lunchHargas[$i] ?? 0);
                    if ($harga <= 0) {
                        $missingHarga = true;
                    }
                }
            }

            if ($totalClaims === 0) {
                return back()->withInput()->withErrors(['lunch_pax' => 'Sila tuntut sekurang-kurangnya untuk satu hari.']);
            }

            if ($hasFutureDate) {
                return back()->withInput()->withErrors(['lunch_dates' => 'Tarikh tuntutan tidak boleh pada masa hadapan.']);
            }

            if ($missingButiran) {
                return back()->withInput()->withErrors(['lunch_butirans' => 'Butiran lunch tidak boleh dikosongkan bagi hari yang dituntut.']);
            }

            if ($missingHarga) {
                return back()->withInput()->withErrors(['lunch_hargas' => 'Sila masukkan harga per pax yang sah untuk hari yang dituntut.']);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments', 'public');
            }

            // Create claims
            for ($i = 0; $i < 7; $i++) {
                $pax = intval($lunchPaxes[$i] ?? 0);
                if ($pax > 0) {
                    $butiran = trim($lunchButirans[$i]);
                    $harga = floatval($lunchHargas[$i]);
                    $nilai = $pax * $harga;
                    
                    $claim = Tuntutan::create([
                        'user_id'         => Auth::id(),
                        'nama_item'       => "{$butiran} ({$pax} pax @ RM " . number_format($harga, 2) . "/pax)",
                        'tag'             => 'Lunch',
                        'nilai_tuntutan'  => $nilai,
                        'tarikh_beli'     => $lunchDates[$i],
                        'minggu_tuntutan' => $week,
                        'status'          => 'Dalam Proses',
                        'attachment'      => $attachmentPath,
                    ]);

                    // Log Aktiviti
                    LogAktiviti::create([
                        'user_id'  => Auth::id(),
                        'aktiviti' => "Membuat tuntutan baharu bagi lunch: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
                        'item_id'  => null,
                        'data_baru' => $claim->toArray(),
                    ]);
                }
            }
        }

        return redirect()->route('tuntutan.index')->with('success', 'Tuntutan berjaya dihantar.');
    }

    /**
     * Kemaskini status tuntutan (Hanya untuk Superadmin).
     */
    public function updateStatus(Request $request, Tuntutan $tuntutan)
    {
        if (!Auth::user()->hasRole('Superadmin')) {
            abort(403, 'Hanya Superadmin sahaja dibenarkan menguruskan status tuntutan.');
        }

        $request->validate([
            'status' => 'required|in:Selesai,Ditolak,Dalam Proses',
        ]);

        $oldData = $tuntutan->toArray();
        $tuntutan->update([
            'status' => $request->status,
        ]);

        // Log Aktiviti
        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini status tuntutan ID {$tuntutan->id} ({$tuntutan->nama_item}) kepada '{$tuntutan->status}'.",
            'item_id' => null,
            'data_lama' => $oldData,
            'data_baru' => $tuntutan->toArray(),
        ]);

        return back()->with('success', 'Status tuntutan berjaya dikemaskini.');
    }
}
