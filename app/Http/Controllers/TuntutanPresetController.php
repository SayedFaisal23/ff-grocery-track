<?php

namespace App\Http\Controllers;

use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'payment_workflow' => $validated['payment_workflow'] ?? null,
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

    /**
     * Save the complete drag-and-drop order for one preset group.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(TuntutanPreset::types())],
            'preset_ids' => ['required', 'array'],
            'preset_ids.*' => ['required', 'integer'],
        ]);

        $orderedIds = array_map('intval', $validated['preset_ids']);

        $result = DB::transaction(function () use ($validated, $orderedIds) {
            $presets = TuntutanPreset::query()
                ->forType($validated['type'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $expectedIds = array_map('intval', $presets->modelKeys());

            if (
                count($orderedIds) !== count($expectedIds)
                || count($orderedIds) !== count(array_unique($orderedIds))
                || array_diff($orderedIds, $expectedIds) !== []
            ) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Susunan pilihan tidak sah. Sila muat semula halaman dan cuba lagi.',
                    'errors' => [
                        'preset_ids' => ['Susunan pilihan tidak sah. Sila muat semula halaman dan cuba lagi.'],
                    ],
                ], 422));
            }

            $oldData = [];

            foreach ($presets as $preset) {
                $oldData[] = [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'sort_order' => $preset->sort_order,
                ];
            }

            foreach ($orderedIds as $index => $presetId) {
                TuntutanPreset::query()
                    ->whereKey($presetId)
                    ->update(['sort_order' => $index + 1]);
            }

            $newPresets = TuntutanPreset::query()
                ->whereIn('id', $orderedIds)
                ->get(['id', 'name', 'sort_order'])
                ->sortBy('sort_order');
            $newData = [];

            foreach ($newPresets as $preset) {
                $newData[] = [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'sort_order' => $preset->sort_order,
                ];
            }

            return [$oldData, $newData];
        });

        [$oldData, $newData] = $result;

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => 'Menyusun semula pilihan tuntutan.',
            'data_lama' => $oldData,
            'data_baru' => $newData,
        ]);

        return response()->json([
            'message' => 'Susunan pilihan berjaya disimpan.',
            'preset_ids' => $orderedIds,
        ]);
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
            'payment_workflow' => is_string($request->input('payment_workflow'))
                ? trim($request->input('payment_workflow'))
                : $request->input('payment_workflow'),
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
                Rule::when(
                    $request->input('type') === TuntutanPreset::TYPE_PAYMENT_METHOD,
                    Rule::notIn([Tuntutan::OTHER_PAYMENT_METHOD])
                ),
            ],
            'payment_workflow' => [
                Rule::requiredIf($request->input('type') === TuntutanPreset::TYPE_PAYMENT_METHOD),
                Rule::prohibitedIf($request->input('type') !== TuntutanPreset::TYPE_PAYMENT_METHOD),
                'nullable',
                Rule::in(TuntutanPreset::paymentWorkflows()),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
