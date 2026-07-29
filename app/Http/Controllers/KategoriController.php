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
            'nama' => $this->normalizeName($request->input('nama')),
            'warna' => $this->normalizeColor($request->input('warna')),
        ]);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:categories,nama'],
            'warna' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
        ], [
            'nama.required' => 'Sila masukkan nama kategori.',
            'nama.unique' => 'Kategori ini telah wujud.',
            'warna.required' => 'Sila pilih warna kategori.',
            'warna.regex' => 'Warna kategori mestilah dalam format heksadesimal #RRGGBB.',
        ]);

        $category = Kategori::create([
            'nama' => trim($validated['nama']),
            'warna' => $validated['warna'],
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
        $categoriesInput = $request->input('categories', []);
        $normalizedCategories = is_array($categoriesInput)
            ? collect($categoriesInput)->mapWithKeys(function ($category, $id): array {
                $category = is_array($category) ? $category : [];

                return [(string) $id => [
                    'nama' => $this->normalizeName($category['nama'] ?? null),
                    'warna' => $this->normalizeColor($category['warna'] ?? null),
                ]];
            })->all()
            : $categoriesInput;

        $request->merge([
            'categories' => $normalizedCategories,
        ]);

        $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'array'],
            'categories.*.nama' => ['required', 'string', 'max:255'],
            'categories.*.warna' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
        ], [
            'categories.required' => 'Tiada kategori untuk disimpan.',
            'categories.*.nama.required' => 'Nama kategori tidak boleh dikosongkan.',
            'categories.*.warna.required' => 'Sila pilih warna kategori.',
            'categories.*.warna.regex' => 'Warna kategori mestilah dalam format heksadesimal #RRGGBB.',
        ]);

        $normalizedCategories = collect($request->input('categories'));

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
                ->replace($normalizedCategories->map(fn (array $category) => $category['nama']));

            $normalizedNames = $targetNames->map(fn ($name) => mb_strtolower($name, 'UTF-8'));

            if ($normalizedNames->unique()->count() !== $normalizedNames->count()) {
                throw ValidationException::withMessages([
                    'categories' => 'Nama kategori mestilah unik.',
                ]);
            }

            $changedCategories = $normalizedCategories
                ->filter(function (array $category, $id) use ($categories): bool {
                    $storedCategory = $categories->get((string) $id);

                    return $storedCategory->nama !== $category['nama']
                        || $storedCategory->warna !== $category['warna'];
                });

            if ($changedCategories->isEmpty()) {
                return 0;
            }

            $oldData = $changedCategories
                ->map(fn ($category, $id) => $categories->get((string) $id)->only(['id', 'nama', 'warna']))
                ->values()
                ->all();

            $nameChanges = $changedCategories
                ->filter(fn (array $category, $id) => $categories->get((string) $id)->nama !== $category['nama']);

            foreach ($nameChanges as $id => $category) {
                $categories->get((string) $id)->update([
                    'nama' => '__kategori_sementara_'.$id.'_'.Str::uuid(),
                ]);
            }

            foreach ($changedCategories as $id => $category) {
                $categories->get((string) $id)->update($category);
            }

            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Mengemaskini {$changedCategories->count()} kategori inventori secara pukal.",
                'data_lama' => $oldData,
                'data_baru' => $changedCategories
                    ->map(fn ($category, $id) => [
                        'id' => (int) $id,
                        'nama' => $category['nama'],
                        'warna' => $category['warna'],
                    ])
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

    private function normalizeName(mixed $name): mixed
    {
        return is_string($name) ? trim($name) : $name;
    }

    private function normalizeColor(mixed $warna): mixed
    {
        return is_string($warna) ? strtoupper(trim($warna)) : $warna;
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
