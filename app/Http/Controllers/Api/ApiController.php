<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventori;
use App\Models\Kategori;
use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    /**
     * Log masuk & jana api_token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = Str::random(60);
            $user->update(['api_token' => $token]);

            LogAktiviti::create([
                'user_id' => $user->id,
                'aktiviti' => 'Pengguna berjaya log masuk ke dalam sistem melalui Android API.',
            ]);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
                ],
            ]);
        }

        return response()->json([
            'message' => 'Maklumat log masuk yang diberikan tidak sepadan dengan rekod kami.',
        ], 422);
    }

    /**
     * Log keluar & padam token.
     */
    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            LogAktiviti::create([
                'user_id' => $user->id,
                'aktiviti' => 'Pengguna berjaya log keluar dari sistem melalui Android API.',
            ]);
            $user->update(['api_token' => null]);
        }

        return response()->json(['message' => 'Berjaya log keluar.']);
    }

    /**
     * Dapatkan maklumat pengguna semasa.
     */
    public function user()
    {
        $user = Auth::user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
        ]);
    }

    /**
     * Senarai Inventori.
     */
    public function inventoriList(Request $request)
    {
        $query = Inventori::with('kategoriPreset');

        if ($request->filled('carian')) {
            $query->where('nama_item', 'like', '%'.$request->carian.'%');
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->kategori_id);
        } elseif ($request->filled('kategori')) {
            $query->whereHas('kategoriPreset', function ($categoryQuery) use ($request) {
                $categoryQuery->where('nama', $request->kategori);
            });
        }

        $items = $query->orderBy('nama_item', 'asc')->get();
        $categories = Kategori::orderBy('nama')->get();

        return response()->json([
            'items' => $items,
            'kategoriSenarai' => $categories->pluck('nama'),
            'categories' => $categories,
        ]);
    }

    public function kategoriList()
    {
        return response()->json(
            Kategori::orderBy('nama')->get()
        );
    }

    /**
     * Senarai Perlu Restok.
     */
    public function restokList()
    {
        $habisStok = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', 0)
            ->orderBy('nama_item')
            ->get();

        $bawahAmbang = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', '>', 0)
            ->whereColumn('jumlah_belum_dibuka', '<=', 'had_ambang')
            ->orderBy('nama_item')
            ->get();

        return response()->json([
            'habisStok' => $habisStok,
            'bawahAmbang' => $bawahAmbang,
        ]);
    }

    /**
     * Tambah Barang.
     */
    public function inventoriStore(Request $request)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $this->resolveKategoriId($request);

        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ]);

        $validated['jejak_luput'] = $request->input('jejak_luput', false);
        $validated['dicipta_oleh'] = Auth::id();
        $validated['dikemaskini_oleh'] = Auth::id();

        $item = Inventori::create($validated);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah item baharu melalui API: {$item->nama_item}.",
            'item_id' => $item->id,
            'data_baru' => $item->toArray(),
        ]);

        return response()->json($item, 201);
    }

    /**
     * Kemaskini Barang.
     */
    public function inventoriUpdate(Request $request, Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $this->resolveKategoriId($request);

        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ]);

        $validated['jejak_luput'] = $request->input('jejak_luput', false);
        $validated['dikemaskini_oleh'] = Auth::id();

        $oldData = $inventori->toArray();
        $oldBelumDibuka = $inventori->jumlah_belum_dibuka;

        $inventori->update($validated);

        if ($inventori->jumlah_belum_dibuka === 0 && $oldBelumDibuka > 0) {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Baki item mencapai kosong: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        } else {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Mengemaskini maklumat stok bagi item melalui API: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return response()->json($inventori);
    }

    /**
     * Pelarasan Kuantiti Pantas.
     */
    public function inventoriAdjust(Request $request, Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
        ]);

        $oldData = $inventori->toArray();
        $oldBelumDibuka = $inventori->jumlah_belum_dibuka;

        $inventori->update([
            'jumlah_belum_dibuka' => $request->jumlah_belum_dibuka,
            'peratus_baki' => $request->peratus_baki,
            'dikemaskini_oleh' => Auth::id(),
        ]);

        if ($inventori->jumlah_belum_dibuka === 0 && $oldBelumDibuka > 0) {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Baki item mencapai kosong: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        } else {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Melaraskan kuantiti/peratus baki bagi item melalui API: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return response()->json($inventori);
    }

    /**
     * Padam Barang.
     */
    public function inventoriDestroy(Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $oldData = $inventori->toArray();
        $itemName = $inventori->nama_item;

        $inventori->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam item inventori melalui API: {$itemName}.",
            'item_id' => null,
            'data_lama' => $oldData,
        ]);

        return response()->json(['message' => 'Barang berjaya dipadam.']);
    }

    /**
     * Senarai Tuntutan.
     */
    public function tuntutanList()
    {
        $user = Auth::user();
        $query = Tuntutan::with('user');

        if ($user->hasRole('Stocker')) {
            $query->where('user_id', $user->id);
        }

        $claims = $query->orderBy('tarikh_beli', 'desc')->get();

        return response()->json($claims);
    }

    /**
     * Hantar Tuntutan.
     */
    public function tuntutanStore(Request $request)
    {
        if (! Auth::user()->hasRole('Stocker')) {
            return response()->json(['message' => 'Hanya Stocker sahaja dibenarkan membuat tuntutan.'], 403);
        }

        $tag = $request->input('tag');
        if (! in_array($tag, ['Stok', 'Lunch', 'General', 'Food'])) {
            return response()->json(['message' => 'Jenis tuntutan tidak sah.'], 422);
        }

        if ($tag === 'Stok' || $tag === 'General' || $tag === 'Food') {
            $request->validate([
                'nama_item' => 'required|string|max:255',
                'tag' => 'required|in:Stok,General,Food',
                'nilai_tuntutan' => 'required|numeric|min:0.01',
                'tarikh_beli' => 'required|date|before_or_equal:today',
                'attachment' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            ]);

            $date = Carbon::parse($request->tarikh_beli);
            $year = $date->format('o');
            $week = sprintf('%02d', $date->weekOfYear);

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments', 'public');
            }

            $claim = Tuntutan::create([
                'nama_item' => $request->nama_item,
                'tag' => $tag,
                'nilai_tuntutan' => $request->nilai_tuntutan,
                'tarikh_beli' => $request->tarikh_beli,
                'minggu_tuntutan' => "{$year}-W{$week}",
                'user_id' => Auth::id(),
                'status' => 'Dalam Proses',
                'attachment' => $attachmentPath,
            ]);

            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Membuat tuntutan baharu bagi pembelian melalui API: {$claim->nama_item} [{$claim->tag}] bernilai RM{$claim->nilai_tuntutan}.",
                'data_baru' => $claim->toArray(),
            ]);

            return response()->json($claim, 201);
        } else {
            // Lunch Claim
            $request->validate([
                'week' => 'required|regex:/^\d{4}-W\d{2}$/',
                'lunch_dates' => 'required|array|size:7',
                'lunch_dates.*' => 'required|date',
                'lunch_butirans' => 'required|array|size:7',
                'lunch_pax' => 'required|array|size:7',
                'lunch_pax.*' => 'nullable|integer|min:0',
                'lunch_hargas' => 'required|array|size:7',
                'lunch_hargas.*' => 'nullable|numeric|min:0',
                'attachment' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            ]);

            $lunchDates = $request->input('lunch_dates');
            $lunchButirans = $request->input('lunch_butirans');
            $lunchPaxes = $request->input('lunch_pax');
            $lunchHargas = $request->input('lunch_hargas');
            $week = $request->input('week');

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('attachments', 'public');
            }

            $createdClaims = [];

            for ($i = 0; $i < 7; $i++) {
                $pax = intval($lunchPaxes[$i] ?? 0);
                if ($pax > 0) {
                    $butiran = trim($lunchButirans[$i] ?? 'Lunch Claim');
                    $harga = floatval($lunchHargas[$i] ?? 5.00);
                    $nilai = $pax * $harga;

                    $claim = Tuntutan::create([
                        'user_id' => Auth::id(),
                        'nama_item' => "{$butiran} ({$pax} pax @ RM ".number_format($harga, 2).'/pax)',
                        'tag' => 'Lunch',
                        'nilai_tuntutan' => $nilai,
                        'tarikh_beli' => $lunchDates[$i],
                        'minggu_tuntutan' => $week,
                        'status' => 'Dalam Proses',
                        'attachment' => $attachmentPath,
                    ]);

                    LogAktiviti::create([
                        'user_id' => Auth::id(),
                        'aktiviti' => "Membuat tuntutan baharu bagi lunch melalui API: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
                        'data_baru' => $claim->toArray(),
                    ]);

                    $createdClaims[] = $claim;
                }
            }

            return response()->json([
                'message' => 'Tuntutan lunch berjaya dihantar.',
                'claims' => $createdClaims,
            ], 201);
        }
    }

    /**
     * Kemaskini Status Tuntutan.
     */
    public function tuntutanUpdateStatus(Request $request, Tuntutan $tuntutan)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Hanya Superadmin dibenarkan mengurus status tuntutan.'], 403);
        }

        $request->validate([
            'status' => 'required|in:Selesai,Ditolak,Dalam Proses',
        ]);

        $oldData = $tuntutan->toArray();
        $tuntutan->update([
            'status' => $request->status,
        ]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini status tuntutan ID {$tuntutan->id} ({$tuntutan->nama_item}) kepada '{$tuntutan->status}' melalui API.",
            'data_lama' => $oldData,
            'data_baru' => $tuntutan->toArray(),
        ]);

        return response()->json($tuntutan);
    }

    /**
     * Senarai Pengguna (Superadmin sahaja).
     */
    public function penggunaList()
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $users = User::with('roles')->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($users);
    }

    /**
     * Tambah Pengguna.
     */
    public function penggunaStore(Request $request)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:Superadmin,Stocker,Tracker',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mendaftar pengguna baharu: {$user->name} dengan peranan {$request->role} melalui API.",
            'data_baru' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->role,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ], 201);
    }

    /**
     * Kemaskini Pengguna.
     */
    public function penggunaUpdate(Request $request, User $user)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:Superadmin,Stocker,Tracker',
        ]);

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        $user->syncRoles([$request->role]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini maklumat pengguna: {$user->name} melalui API.",
            'data_lama' => $oldData,
            'data_baru' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->role,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Padam Pengguna.
     */
    public function penggunaDestroy(User $user)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Anda tidak boleh memadam akaun anda sendiri.'], 400);
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        $userName = $user->name;
        $user->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam pengguna: {$userName} melalui API.",
            'data_lama' => $oldData,
        ]);

        return response()->json(['message' => 'Pengguna berjaya dipadam.']);
    }

    /**
     * Senarai Log Aktiviti (Superadmin sahaja).
     */
    public function logAktivitiList()
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $logs = LogAktiviti::with('user.roles')
            ->orderBy('created_at', 'desc')
            ->take(150)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'user_name' => $log->user?->name ?? 'Sistem / Pengguna Dipadam',
                    'user_role' => $log->user?->roles->first()?->name ?? 'Tiada Peranan',
                    'aktiviti' => $log->aktiviti,
                    'data_lama' => $log->data_lama,
                    'data_baru' => $log->data_baru,
                ];
            });

        return response()->json($logs);
    }

    private function resolveKategoriId(Request $request): void
    {
        if ($request->filled('kategori_id') || ! $request->filled('kategori')) {
            return;
        }

        $categoryId = Kategori::where('nama', $request->input('kategori'))->value('id');

        if ($categoryId) {
            $request->merge(['kategori_id' => $categoryId]);
        }
    }
}
