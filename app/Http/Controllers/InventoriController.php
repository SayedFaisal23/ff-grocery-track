<?php

namespace App\Http\Controllers;

use App\Models\Inventori;
use App\Models\Kategori;
use App\Models\LogAktiviti;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventoriController extends Controller
{
    private const INVENTORY_SORTS = [
        'nama_asc',
        'nama_desc',
        'kategori_asc',
        'kategori_desc',
        'baki_asc',
        'baki_desc',
        'tarikh_luput_asc',
        'tarikh_luput_desc',
    ];

    /**
     * Paparkan senarai inventori.
     */
    public function index(Request $request)
    {
        $query = Inventori::with('kategoriPreset');

        $today = now()->startOfDay();
        $inventorySummary = [
            'totalItems' => Inventori::count(),
            'totalUnits' => (int) Inventori::sum('jumlah_belum_dibuka'),
            'outOfStock' => Inventori::where('jumlah_belum_dibuka', 0)->count(),
            'belowThreshold' => Inventori::where('jumlah_belum_dibuka', '>', 0)
                ->whereColumn('jumlah_belum_dibuka', '<=', 'had_ambang')
                ->count(),
            'expired' => Inventori::where('jejak_luput', true)
                ->whereNotNull('tarikh_luput')
                ->where('tarikh_luput', '<', $today)
                ->count(),
        ];

        // Carian nama item
        if ($request->filled('carian')) {
            $query->where('nama_item', 'like', '%'.$request->carian.'%');
        }

        // Penapisan kategori
        if ($request->filled('kategori')) {
            $query->where('kategori_id', $request->kategori);
        }

        $activeSort = $this->normalizedInventorySort($request->query('sort'));
        $this->applyInventorySort($query, $activeSort);

        $items = $query->get();
        $kategoriSenarai = Kategori::orderBy('nama')->get();

        return view('inventori.index', compact('items', 'kategoriSenarai', 'activeSort', 'inventorySummary'));
    }

    private function normalizedInventorySort(mixed $sort): ?string
    {
        return is_string($sort) && in_array($sort, self::INVENTORY_SORTS, true)
            ? $sort
            : null;
    }

    private function formatExpiryDateForStorage(?string $expiryDate): ?string
    {
        return $expiryDate
            ? Carbon::createFromFormat('!d/m/Y', $expiryDate)->toDateString()
            : null;
    }

    private function applyInventorySort(Builder $query, ?string $sort): void
    {
        switch ($sort) {
            case 'nama_desc':
                $query->orderBy('nama_item', 'desc');
                break;

            case 'kategori_asc':
            case 'kategori_desc':
                $direction = $sort === 'kategori_asc' ? 'asc' : 'desc';

                $query->orderBy(
                    Kategori::select('nama')
                        ->whereColumn('categories.id', 'inventori.kategori_id'),
                    $direction
                )->orderBy('nama_item', 'asc');
                break;

            case 'baki_asc':
            case 'baki_desc':
                $direction = $sort === 'baki_asc' ? 'asc' : 'desc';

                $query->orderBy('jumlah_belum_dibuka', $direction)
                    ->orderBy('nama_item', 'asc');
                break;

            case 'tarikh_luput_asc':
            case 'tarikh_luput_desc':
                $direction = $sort === 'tarikh_luput_asc' ? 'asc' : 'desc';

                $query->orderByRaw(
                    'CASE WHEN jejak_luput = 1 AND tarikh_luput IS NOT NULL THEN 0 ELSE 1 END'
                )->orderBy('tarikh_luput', $direction)
                    ->orderBy('nama_item', 'asc');
                break;

            case 'nama_asc':
            default:
                $query->orderBy('nama_item', 'asc');
                break;
        }
    }

    /**
     * Tunjukkan senarai barang perlu direstok (habis stok atau bawah ambang).
     */
    public function restockList()
    {
        // Barang yang habis stok sepenuhnya
        $habisStok = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', 0)
            ->orderBy('nama_item')
            ->get();

        // Barang yang di bawah had ambang
        $bawahAmbang = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', '>', 0)
            ->whereColumn('jumlah_belum_dibuka', '<=', 'had_ambang')
            ->orderBy('nama_item')
            ->get();

        return view('inventori.restok', compact('habisStok', 'bawahAmbang'));
    }

    /**
     * Tunjukkan borang tambah barang baharu.
     */
    public function create()
    {
        // Hanya Superadmin, Stocker dan Tracker boleh tambah item baharu
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk menambah item.');
        }
        $categories = Kategori::orderBy('nama')->get();

        return view('inventori.create', compact('categories'));
    }

    /**
     * Simpan barang baharu.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            abort(403);
        }

        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date_format:d/m/Y',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ], [
            'nama_item.required' => 'Sila masukkan Nama/Jenama.',
            'kategori_id.required' => 'Sila pilih kategori.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak sah.',
            'jumlah_belum_dibuka.required' => 'Sila masukkan baki.',
            'peratus_baki.between' => 'Peratus baki mestilah di antara 0 hingga 100.',
            'tarikh_luput.date_format' => 'Sila gunakan format tarikh dd/mm/yyyy.',
            'had_ambang.required' => 'Sila tetapkan had ambang restok.',
        ]);

        $validated['tarikh_luput'] = $this->formatExpiryDateForStorage($validated['tarikh_luput'] ?? null);
        // Tetapkan nilai laluan untuk jejak_luput (jika tiada dalam input)
        $validated['jejak_luput'] = $request->has('jejak_luput');
        $validated['dicipta_oleh'] = Auth::id();
        $validated['dikemaskini_oleh'] = Auth::id();

        $item = Inventori::create($validated);

        // Log Aktiviti
        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah item baharu: {$item->nama_item}.",
            'item_id' => $item->id,
            'data_baru' => $item->toArray(),
        ]);

        return redirect()->route('inventori.index')->with('success', 'Barang berjaya ditambahkan.');
    }

    /**
     * Paparkan butiran barang.
     */
    public function show(Inventori $inventori)
    {
        return view('inventori.show', compact('inventori'));
    }

    /**
     * Tunjukkan borang kemaskini.
     */
    public function edit(Inventori $inventori)
    {
        $categories = Kategori::orderBy('nama')->get();

        return view('inventori.edit', compact('inventori', 'categories'));
    }

    /**
     * Kemaskini maklumat barang.
     */
    public function update(Request $request, Inventori $inventori)
    {
        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date_format:d/m/Y',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ], [
            'nama_item.required' => 'Sila masukkan Nama/Jenama.',
            'kategori_id.required' => 'Sila pilih kategori.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak sah.',
            'jumlah_belum_dibuka.required' => 'Sila masukkan baki.',
            'peratus_baki.between' => 'Peratus baki mestilah di antara 0 hingga 100.',
            'tarikh_luput.date_format' => 'Sila gunakan format tarikh dd/mm/yyyy.',
            'had_ambang.required' => 'Sila tetapkan had ambang restok.',
        ]);

        $validated['tarikh_luput'] = $this->formatExpiryDateForStorage($validated['tarikh_luput'] ?? null);
        $validated['jejak_luput'] = $request->has('jejak_luput');
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
            // Log kemaskini stok biasa
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Mengemaskini maklumat stok bagi item: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return redirect()->route('inventori.index')->with('success', 'Barang berjaya dikemaskini.');
    }

    /**
     * Kemaskini pantas tahap stok (digunakan pada dashboard / index).
     */
    public function adjustStock(Request $request, Inventori $inventori)
    {
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
                'aktiviti' => "Melaraskan kuantiti/peratus baki bagi item: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return back()->with('success', 'Tahap stok berjaya diselaraskan.');
    }

    /**
     * Padam barang.
     */
    public function destroy(Inventori $inventori)
    {
        // Hanya Superadmin, Stocker dan Tracker boleh padam item
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk memadam item.');
        }

        $oldData = $inventori->toArray();
        $itemName = $inventori->nama_item;

        $inventori->delete();

        // Log Aktiviti
        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam item inventori: {$itemName}.",
            'item_id' => null,
            'data_lama' => $oldData,
        ]);

        return redirect()->route('inventori.index')->with('success', 'Barang berjaya dipadam.');
    }
}
