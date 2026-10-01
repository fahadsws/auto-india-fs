<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Tiny server-side adapter for jQuery DataTables (paging, global search, ordering).
 * Filters are applied by the caller to $query BEFORE calling make(), so "recordsTotal" reflects the active filters.
 */
class DataTable
{
    /**
     * @param  array<int,string|null>  $sortable   DB column per table column index (null = not sortable)
     * @param  string[]  $searchable               DB columns matched by the search box
     * @param  callable  $row                      fn(Model): array of HTML cells, in column order
     */
    public static function make(Builder $query, Request $r, array $sortable, array $searchable, callable $row, array $defaultOrder = ['id', 'desc'])
    {
        $total = (clone $query)->count();

        if (($term = trim((string) data_get($r->input('search'), 'value'))) !== '' && $searchable) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(function ($q) use ($searchable, $like) {
                foreach ($searchable as $col) {
                    if (str_contains($col, '.')) { [$rel, $field] = explode('.', $col, 2); $q->orWhereHas($rel, fn ($r) => $r->where($field, 'like', $like)); }
                    else $q->orWhere($col, 'like', $like);
                }
            });
        }
        $filtered = (clone $query)->count();

        $idx = (int) data_get($r->input('order'), '0.column', -1);
        $dir = strtolower((string) data_get($r->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if (isset($sortable[$idx]) && $sortable[$idx]) $query->orderBy($sortable[$idx], $dir);
        else $query->orderBy($defaultOrder[0], $defaultOrder[1]);
        if ($sortable && ($sortable[$idx] ?? null) !== $defaultOrder[0]) $query->orderBy($defaultOrder[0], 'desc');

        $length = (int) $r->input('length', 15);
        $length = $length < 1 ? 500 : min($length, 500);
        $rows = $query->skip(max(0, (int) $r->input('start', 0)))->take($length)->get();

        return response()->json([
            'draw' => (int) $r->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map($row)->values()->all(),
            'ids' => $rows->map(fn ($m) => $m->getKey())->values()->all(),
        ]);
    }

    /** Apply a "created between" filter from a flatpickr range value like "2026-09-01 to 2026-09-26". */
    public static function dateRange(Builder $q, ?string $range, string $column = 'created_at'): Builder
    {
        if (! $range) return $q;
        $parts = preg_split('/\s+(?:to|—|–)\s+/', trim($range));
        try {
            $from = \Carbon\Carbon::parse($parts[0])->startOfDay();
            $to = \Carbon\Carbon::parse($parts[1] ?? $parts[0])->endOfDay();
            $q->whereBetween($column, [$from, $to]);
        } catch (\Throwable) {
        }
        return $q;
    }
}
