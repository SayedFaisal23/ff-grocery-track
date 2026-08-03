<?php

namespace App\Http\Controllers;

use App\Models\LogAktiviti;
use App\Models\TuntutanPreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TuntutanPresetController extends Controller
{
    public function index()
    {
        $presets = TuntutanPreset::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return view('tuntutan_preset.index', [
            'platforms' => $presets->get(TuntutanPreset::TYPE_PURCHASE_PLATFORM, collect()),
            'paymentMethods' => $presets->get(TuntutanPreset::TYPE_PAYMENT_METHOD, collect()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePreset($request);

        $preset = DB::transaction(function () use ($validated) {
            $lastSortOrder = TuntutanPreset::query()
                ->forType($validated['type'])
                ->orderByDesc('sort_order')
                ->lockForUpdate()
                ->value('sort_order');

            $nextSortOrder = ((int) $lastSortOrder) + 1;

            return TuntutanPreset::create(array_merge($validated, ['sort_order' => $nextSortOrder]));
        });

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah pilihan tuntutan {$preset->name}.",
            'data_baru' => $preset->toArray(),
        ]);

        return redirect()->route('tuntutan-preset.index')->with('success', 'Pilihan berjaya ditambah.');
    }

    public function update(Request $request, TuntutanPreset $tuntutanPreset)
    {
        $validated = $this->validatePreset($request, $tuntutanPreset);
        $oldData = $tuntutanPreset->toArray();

        $tuntutanPreset->update([
            'name' => $validated['name'],
            'sort_order' => $request->integer('sort_order', $tuntutanPreset->sort_order),
        ]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini pilihan tuntutan {$tuntutanPreset->name}.",
            'data_lama' => $oldData,
            'data_baru' => $tuntutanPreset->toArray(),
        ]);

        return redirect()->route('tuntutan-preset.index')->with('success', 'Pilihan berjaya dikemaskini.');
    }

    public function destroy(TuntutanPreset $tuntutanPreset)
    {
        $oldData = $tuntutanPreset->toArray();
        $name = $tuntutanPreset->name;
        $tuntutanPreset->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam pilihan tuntutan {$name}.",
            'data_lama' => $oldData,
        ]);

        return redirect()->route('tuntutan-preset.index')->with('success', 'Pilihan berjaya dipadam.');
    }

    private function validatePreset(Request $request, ?TuntutanPreset $preset = null): array
    {
        $request->merge([
            'type' => $preset?->type ?? (is_string($request->input('type')) ? trim($request->input('type')) : $request->input('type')),
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
        ]);

        return $request->validate([
            'type' => ['required', Rule::in(TuntutanPreset::types())],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tuntutan_presets', 'name')
                    ->where(fn ($query) => $query->where('type', $request->input('type')))
                    ->ignore($preset),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
