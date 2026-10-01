<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;
use App\Http\Controllers\Controller;
use App\Models\CarComparison;
use App\Models\VehicleModel;
use App\Support\DataTable;
use App\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComparisonController extends Controller
{
    use HandlesBulk;

    public function index()
    {
        return view('admin.comparisons.index');
    }

    public function data(Request $r)
    {
        $q = CarComparison::query()
            ->join('vehicle_models as a', 'a.id', '=', 'car_comparisons.car_a_id')
            ->join('vehicle_models as b', 'b.id', '=', 'car_comparisons.car_b_id')
            ->select('car_comparisons.*')
            ->with(['carA.brandMaster', 'carB.brandMaster', 'winner.brandMaster'])
            ->when($r->active !== null && $r->active !== '', fn ($x) => $x->where('car_comparisons.is_active', (bool) $r->active))
            ->when($r->featured !== null && $r->featured !== '', fn ($x) => $x->where('car_comparisons.is_featured', (bool) $r->featured));
        DataTable::dateRange($q, $r->range, 'car_comparisons.updated_at');

        return DataTable::make($q, $r, ['a.name', 'car_comparisons.is_featured', 'car_comparisons.is_active', 'car_comparisons.updated_at', null],
            ['car_comparisons.title', 'a.name', 'b.name'],
            fn (CarComparison $c) => [
                '<div class="d-flex align-items-center gap-2"><img class="thumb-sm" src="'.e($c->carA->hero_url).'" alt=""><span class="text-muted small fw-bold">VS</span><img class="thumb-sm me-2" src="'.e($c->carB->hero_url).'" alt="">'
                    .'<div><a href="'.e(route('admin.comparisons.edit', $c)).'" class="fw-medium text-heading">'.e($c->heading).'</a>'
                    .($c->winner ? '<br><small class="text-muted">Pick: '.e($c->winner->full_name).'</small>' : '').'</div></div>',
                Ui::yesNo($c->is_featured), Ui::yesNo($c->is_active), '<span class="small">'.e($c->updated_at?->format('d M Y')).'</span>',
                Ui::actions(['view' => $c->url, 'view_title' => 'View on website', 'edit' => route('admin.comparisons.edit', $c), 'delete' => route('admin.comparisons.destroy', $c), 'delete_msg' => 'Delete this suggested comparison? The two cars can still be compared on the site.']),
            ], ['car_comparisons.id', 'desc']);
    }

    public function create(Request $r)
    {
        return $this->form(new CarComparison(['is_active' => true, 'car_a_id' => $r->query('a'), 'car_b_id' => $r->query('b')]));
    }

    public function edit(CarComparison $comparison) { return $this->form($comparison); }

    public function store(Request $r) { return $this->save($r, new CarComparison()); }

    public function update(Request $r, CarComparison $comparison) { return $this->save($r, $comparison); }

    public function destroy(CarComparison $comparison)
    {
        $comparison->delete();
        return redirect()->route('admin.comparisons.index')->with('success', 'Comparison removed.');
    }

    private function form(CarComparison $comparison)
    {
        return view('admin.comparisons.form', [
            'comparison' => $comparison,
            'cars' => VehicleModel::with('brandMaster')->get()->sortBy('full_name')->values(),
        ]);
    }

    private function save(Request $r, CarComparison $c)
    {
        $d = $r->validate([
            'car_a_id' => 'required|integer|exists:vehicle_models,id', 'car_b_id' => 'required|integer|different:car_a_id|exists:vehicle_models,id',
            'winner_id' => 'nullable|integer', 'title' => 'nullable|string|max:160', 'intro' => 'nullable|string|max:2000', 'verdict' => 'nullable|string|max:5000',
            'meta_title' => 'nullable|string|max:70', 'meta_description' => 'nullable|string|max:320', 'sort_order' => 'nullable|integer|min:0|max:65535',
        ], ['car_b_id.different' => 'Pick two different cars.']);

        if (! empty($d['winner_id']) && ! in_array((int) $d['winner_id'], [(int) $d['car_a_id'], (int) $d['car_b_id']], true)) {
            throw ValidationException::withMessages(['winner_id' => 'The pick must be one of the two cars.']);
        }
        if (CarComparison::forPair((int) $d['car_a_id'], (int) $d['car_b_id'])->when($c->exists, fn ($x) => $x->whereKeyNot($c->id))->exists()) {
            throw ValidationException::withMessages(['car_b_id' => 'This pair already exists (in either order).']);
        }

        $c->fill([
            'car_a_id' => $d['car_a_id'], 'car_b_id' => $d['car_b_id'], 'winner_id' => $d['winner_id'] ?: null,
            'title' => trim((string) ($d['title'] ?? '')) ?: null, 'intro' => trim((string) ($d['intro'] ?? '')) ?: null, 'verdict' => trim((string) ($d['verdict'] ?? '')) ?: null,
            'meta_title' => $d['meta_title'] ?? null, 'meta_description' => $d['meta_description'] ?? null,
            'sort_order' => $d['sort_order'] ?? 0, 'is_active' => $r->boolean('is_active'), 'is_featured' => $r->boolean('is_featured'),
        ])->save();

        return redirect()->route('admin.comparisons.index')->with('success', 'Comparison saved.');
    }

    protected function bulkBase(Request $r): Builder { return CarComparison::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable (show)', 'do' => fn (CarComparison $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable (hide)', 'do' => fn (CarComparison $m) => $m->update(['is_active' => false]) || true],
            'feature' => ['label' => 'Mark as featured', 'do' => fn (CarComparison $m) => $m->update(['is_featured' => true]) || true],
            'unfeature' => ['label' => 'Remove from featured', 'do' => fn (CarComparison $m) => $m->update(['is_featured' => false]) || true],
            'delete' => ['label' => 'Delete permanently', 'danger' => true, 'confirm' => 'This cannot be undone.', 'do' => fn (CarComparison $m) => (bool) $m->delete()],
        ];
    }
}
