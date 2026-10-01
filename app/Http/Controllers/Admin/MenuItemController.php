<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index(Request $r)
    {
        $location = $r->query('location') === 'footer' ? 'footer' : 'header';
        $items = MenuItem::where('location', $location)->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('id')->with('children')->get();

        return view('admin.menus.index', compact('items', 'location'));
    }

    public function create(Request $r)
    {
        $item = new MenuItem([
            'location' => $r->query('location') === 'footer' ? 'footer' : 'header',
            'parent_id' => $r->query('parent'), 'is_active' => true, 'sort_order' => 0,
        ]);
        if ($item->parent_id && $p = MenuItem::find($item->parent_id)) {
            $item->location = $p->location;
        }

        return view('admin.menus.form', ['item' => $item, 'parents' => $this->parents()]);
    }

    public function edit(MenuItem $menu) { return view('admin.menus.form', ['item' => $menu, 'parents' => $this->parents($menu)]); }

    public function store(Request $r)
    {
        $item = MenuItem::create($this->payload($r));

        return redirect()->route('admin.menus.index', ['location' => $item->location])->with('success', 'Menu item added.');
    }

    public function update(Request $r, MenuItem $menu)
    {
        $menu->update($this->payload($r, $menu));

        return redirect()->route('admin.menus.index', ['location' => $menu->location])->with('success', 'Menu item updated.');
    }

    public function destroy(MenuItem $menu)
    {
        $menu->delete();

        return back()->with('success', 'Menu item deleted.');
    }

    private function parents(?MenuItem $except = null)
    {
        return MenuItem::whereNull('parent_id')->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->orderBy('location')->orderBy('sort_order')->get();
    }

    private function payload(Request $r, ?MenuItem $current = null): array
    {
        $d = $r->validate([
            'location' => 'required|in:header,footer',
            'parent_id' => 'nullable|exists:menu_items,id',
            'title' => 'required|string|max:100',
            'url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://|mailto:|tel:|\#)#i'],
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ], ['url.regex' => 'URL must start with /, http://, https://, mailto: or tel:.']);

        if ($d['parent_id'] ?? null) {
            $parent = MenuItem::find($d['parent_id']);
            abort_if($parent->parent_id || ($current && $parent->id === $current->id), 422, 'Sub-items can only sit under a top-level item.');
            abort_if($current && $current->children()->exists(), 422, 'This item has sub-items, so it cannot become a sub-item.');
            $d['location'] = $parent->location;
        }
        $d['sort_order'] = (int) ($d['sort_order'] ?? 0);
        $d['is_active'] = $r->boolean('is_active');
        $d['open_new_tab'] = $r->boolean('open_new_tab');

        return $d;
    }
}
