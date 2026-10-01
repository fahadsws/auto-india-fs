<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Bulk actions for a DataTable list.
 *
 * A controller supplies:
 *   bulkBase()      the scoped query (permission rules, e.g. authors only see their own articles)
 *   bulkActions()   key => ['label', 'do' => fn(Model, Request): bool, 'confirm'?, 'danger'?, 'options'?: [[value,label],...]]
 *   bulkFiltered()  (optional) the list's current filtered query, enabling "select all N matching the filters"
 *   bulkSearchable  (optional) columns used by the search box
 * Each record is processed through Eloquent, so model events (knowledge-base indexing, file cleanup) still run.
 */
trait HandlesBulk
{
    protected int $bulkLimit = 2000;

    abstract protected function bulkBase(Request $r): Builder;

    abstract protected function bulkActions(Request $r): array;

    protected function bulkFiltered(Request $r): ?Builder { return null; }

    protected function bulkSearchable(): array { return []; }

    /** Bulk configuration for the list view; injected into the dt partial by a view composer (AppServiceProvider). */
    public function bulkViewData(Request $r): array
    {
        return [
            'bulk' => collect($this->bulkActions($r))->map(fn ($a, $k) => [
                'key' => $k, 'label' => $a['label'], 'confirm' => $a['confirm'] ?? null, 'danger' => $a['danger'] ?? false, 'options' => $a['options'] ?? null,
            ])->values()->all(),
            'bulkUrl' => route($this->bulkRoute()),
            'bulkAll' => $this->bulkFiltered($r) !== null,
        ];
    }

    /** Route name of the bulk endpoint, e.g. admin.articles.bulk (derived from the controller name). */
    protected function bulkRoute(): string
    {
        return $this->bulkRouteName ?? 'admin.'.\Illuminate\Support\Str::of(class_basename($this))->beforeLast('Controller')->kebab()->plural().'.bulk';
    }

    public function bulk(Request $r)
    {
        $r->validate(['action' => 'required|string', 'ids' => 'nullable|array', 'ids.*' => 'integer', 'all' => 'nullable', 'value' => 'nullable|string|max:100', 'search' => 'nullable|string|max:200']);
        $actions = $this->bulkActions($r);
        $action = $actions[$r->input('action')] ?? null;
        abort_if(! $action, 422, 'That action is not available to you.');

        if ($r->boolean('all') && ($filtered = $this->bulkFiltered($r)) !== null) {
            if (($term = trim((string) $r->input('search'))) !== '' && $this->bulkSearchable()) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $filtered->where(function ($q) use ($like) { foreach ($this->bulkSearchable() as $c) {
                    if (str_contains($c, '.')) { [$rel, $field] = explode('.', $c, 2); $q->orWhereHas($rel, fn ($r) => $r->where($field, 'like', $like)); }
                    else $q->orWhere($c, 'like', $like);
                } });
            }
            $ids = $filtered->limit($this->bulkLimit)->pluck($filtered->getModel()->getTable().'.id')->all();
        } else {
            $ids = array_slice(array_map('intval', $r->input('ids', [])), 0, $this->bulkLimit);
        }
        if (! $ids) return response()->json(['message' => 'Nothing selected.', 'count' => 0], 422);

        @set_time_limit(280);
        $done = 0; $failed = 0;
        $this->bulkBase($r)->whereIn($this->bulkBase($r)->getModel()->getTable().'.id', $ids)->orderBy('id')->chunkById(100, function ($chunk) use ($action, $r, &$done, &$failed) {
            foreach ($chunk as $m) {
                try { $action['do']($m, $r) ? $done++ : $failed++; } catch (\Throwable) { $failed++; }
            }
        });

        $skipped = count($ids) - $done - $failed; // rows outside the user's scope
        return response()->json([
            'count' => $done,
            'message' => "{$action['label']}: $done done".($failed ? ", $failed skipped" : '').($skipped > 0 ? ", $skipped not permitted" : '').'.',
        ]);
    }
}
