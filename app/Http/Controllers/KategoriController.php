<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\LogAktiviti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KategoriController extends Controller
{
    public function index()
    {
        $categories = Kategori::withCount('inventori')
            ->orderBy('nama')
            ->get();

        return view('kategori.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'nama' => trim((string) $request->input('nama')),
        ]);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:categories,nama'],
        ], [
            'nama.required' => 'Sila masukkan nama kategori.',
            'nama.unique' => 'Kategori ini telah wujud.',
        ]);

        $category = Kategori::create([
            'nama' => trim($validated['nama']),
        ]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah kategori inventori: {$category->nama}.",
            'data_baru' => $category->toArray(),
        ]);

        return redirect()->route('kategori.index')
            ->with('success', 'Kategori berjaya ditambahkan.');
    }

    public function updateAll(Request $request)
    {
        $normalizedCategories = collect($request->input('categories', []))
            ->mapWithKeys(fn ($name, $id) => [(string) $id => trim((string) $name)]);

        $request->merge([
            'categories' => $normalizedCategories->all(),
        ]);

        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', 'max:255'],
        ], [
            'categories.required' => 'Tiada kategori untuk disimpan.',
            'categories.*.required' => 'Nama kategori tidak boleh dikosongkan.',
        ]);

        $updatedCount = DB::transaction(function () use ($normalizedCategories) {
            $categories = Kategori::query()
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Kategori $category) => (string) $category->id);

            $hasInvalidId = $normalizedCategories->keys()
                ->contains(fn ($id) => ! ctype_digit((string) $id) || ! $categories->has((string) $id));

            if ($hasInvalidId) {
                throw ValidationException::withMessages([
                    'categories' => 'Senarai kategori mengandungi rekod yang tidak sah.',
                ]);
            }

            $targetNames = $categories
                ->mapWithKeys(fn (Kategori $category) => [(string) $category->id => $category->nama])
                ->merge($normalizedCategories);

            $normalizedNames = $targetNames->map(fn ($name) => mb_strtolower($name, 'UTF-8'));

            if ($normalizedNames->unique()->count() !== $normalizedNames->count()) {
                throw ValidationException::withMessages([
                    'categories' => 'Nama kategori mestilah unik.',
                ]);
            }

            $changedCategories = $normalizedCategories
                ->filter(fn ($name, $id) => $categories->get((string) $id)->nama !== $name);

            if ($changedCategories->isEmpty()) {
                return 0;
            }

            $oldData = $changedCategories
                ->map(fn ($name, $id) => $categories->get((string) $id)->only(['id', 'nama']))
                ->values()
                ->all();

            foreach ($changedCategories as $id => $name) {
                $categories->get((string) $id)->update([
                    'nama' => '__kategori_sementara_'.$id.'_'.Str::uuid(),
                ]);
            }

            foreach ($changedCategories as $id => $name) {
                $categories->get((string) $id)->update(['nama' => $name]);
            }

            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Mengemaskini {$changedCategories->count()} kategori inventori secara pukal.",
                'data_lama' => $oldData,
                'data_baru' => $changedCategories
                    ->map(fn ($name, $id) => ['id' => (int) $id, 'nama' => $name])
                    ->values()
                    ->all(),
            ]);

            return $changedCategories->count();
        });

        $message = $updatedCount > 0
            ? 'Semua kategori berjaya disimpan.'
            : 'Tiada perubahan kategori untuk disimpan.';

        return redirect()->route('kategori.index')->with('success', $message);
    }

    public function destroy(Kategori $kategori)
    {
        if ($kategori->inventori()->exists()) {
            return redirect()->route('kategori.index')
                ->with('error', 'Kategori yang sedang digunakan tidak boleh dipadam.');
        }

        $oldData = $kategori->toArray();
        $categoryName = $kategori->nama;
        $kategori->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam kategori inventori: {$categoryName}.",
            'data_lama' => $oldData,
        ]);

        return redirect()->route('kategori.index')
            ->with('success', 'Kategori berjaya dipadam.');
    }
}
