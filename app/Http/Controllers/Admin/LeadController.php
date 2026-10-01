<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use HandlesBulk;

    public function index()
    {
        return view('admin.leads.index', [
            'counts' => Lead::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'staff' => User::permission('leads.manage')->orWhereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Current list filters (shared by the table and "select all matching" bulk actions). */
    private function filtered(Request $r)
    {
        $q = Lead::with(['listing', 'assignee'])
            ->when($r->status, fn ($x, $v) => $x->where('status', $v))
            ->when($r->type, fn ($x, $v) => $x->where('type', $v))
            ->when($r->assignee === 'none', fn ($x) => $x->whereNull('assigned_to'))
            ->when($r->assignee && $r->assignee !== 'none', fn ($x) => $x->where('assigned_to', $r->assignee));
        \App\Support\DataTable::dateRange($q, $r->range);

        return $q;
    }

    public function data(Request $r)
    {
        $q = $this->filtered($r);

        return \App\Support\DataTable::make($q, $r, ['name', null, null, 'status', null, 'created_at', null], ['name', 'phone', 'email', 'message'],
            fn (Lead $l) => [
                '<a class="fw-medium text-heading" href="'.e(route('admin.leads.show', $l)).'">'.e($l->name).'</a> '.\App\Support\Ui::badge($l->type, 'secondary'),
                '<span class="small">'.e($l->listing?->title ?? \Illuminate\Support\Str::limit((string) $l->message, 45)).'</span>',
                '<span class="small">'.e($l->phone).'<br><span class="text-muted">'.e($l->email).'</span></span>',
                \App\Support\Ui::status($l->status), e($l->assignee?->name ?? '—'), \App\Support\Ui::date($l->created_at),
                \App\Support\Ui::actions(['view' => route('admin.leads.show', $l), 'view_self' => true, 'view_title' => 'Open lead']),
            ]);
    }
    public function show(Lead $lead)
    {
        $lead->load('listing');
        return view('admin.leads.show', ['lead' => $lead, 'staff' => User::permission('leads.manage')->orWhereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->get()]);
    }

    public function update(Request $r, Lead $lead)
    {
        $d = $r->validate(['status' => 'required|in:new,contacted,won,lost', 'notes' => 'nullable|string|max:3000', 'assigned_to' => 'nullable|exists:users,id']);
        $lead->update($d);
        return back()->with('success', 'Lead updated.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();
        return redirect()->route('admin.leads.index')->with('success', 'Lead deleted.');
    }
    protected function bulkSearchable(): array { return ['name', 'phone', 'email', 'message']; }

    protected function bulkFiltered(Request $r): ?\Illuminate\Database\Eloquent\Builder { return $this->filtered($r); }

    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return Lead::query(); }

    protected function bulkActions(Request $r): array
    {
        if (! $r->user()->can('leads.manage')) return [];
        $staff = User::permission('leads.manage')->orWhereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->orderBy('name')->get(['id', 'name'])->map(fn ($u) => [(string) $u->id, $u->name])->all();
        return [
            'status' => ['label' => 'Change status', 'options' => [['new', 'New'], ['contacted', 'Contacted'], ['won', 'Won'], ['lost', 'Lost']],
                'do' => fn (Lead $m, Request $r) => in_array($r->value, ['new', 'contacted', 'won', 'lost'], true) && $m->update(['status' => $r->value])],
            'assign' => ['label' => 'Assign to', 'options' => array_merge([['', '— Unassigned —']], $staff),
                'do' => fn (Lead $m, Request $r) => $m->update(['assigned_to' => $r->value ?: null]) || true],
            'delete' => ['label' => 'Delete permanently', 'danger' => true, 'confirm' => 'This cannot be undone.', 'do' => fn (Lead $m) => (bool) $m->delete()],
        ];
    }
}
