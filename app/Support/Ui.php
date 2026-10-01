<?php

namespace App\Support;

/** HTML snippets for DataTable cells (everything user-supplied is escaped). */
class Ui
{
    public static function badge(?string $text, string $color = 'secondary'): string
    {
        return '<span class="badge bg-label-'.e($color).'">'.e($text).'</span>';
    }

    public static function status(?string $s): string
    {
        $map = ['published' => 'success', 'active' => 'success', 'launched' => 'success', 'won' => 'success', 'ok' => 'success', 'draft' => 'secondary', 'hidden' => 'secondary',
            'skipped' => 'secondary', 'lost' => 'secondary', 'discontinued' => 'secondary', 'scheduled' => 'info', 'upcoming' => 'info', 'new' => 'danger', 'error' => 'danger',
            'contacted' => 'warning', 'sold' => 'warning', 'facelift' => 'warning'];
        return self::badge($s, $map[$s] ?? 'secondary');
    }

    public static function thumb(?string $url, string $title, ?string $sub = null, ?string $href = null): string
    {
        $t = $href ? '<a href="'.e($href).'" class="fw-medium text-heading">'.e($title).'</a>' : '<span class="fw-medium">'.e($title).'</span>';
        return '<div class="d-flex align-items-center">'.($url ? '<img class="thumb-sm me-3" src="'.e($url).'" alt="" loading="lazy">' : '')
            .'<div>'.$t.($sub ? '<br><small class="text-muted">'.e($sub).'</small>' : '').'</div></div>';
    }

    public static function yesNo(bool $v): string
    {
        return self::badge($v ? 'Yes' : 'No', $v ? 'success' : 'secondary');
    }

    /** @param array{view?:string,edit?:string,delete?:string,delete_msg?:string,extra?:string} $a */
    public static function actions(array $a): string
    {
        $h = '<div class="text-end text-nowrap">'.($a['extra'] ?? '');
        if (! empty($a['view'])) $h .= '<a href="'.e($a['view']).'"'.(empty($a['view_self']) ? ' target="_blank" rel="noopener"' : '').' class="btn btn-sm btn-icon btn-text-secondary" title="'.e($a['view_title'] ?? 'View').'"><i class="ti ti-eye"></i></a>';
        if (! empty($a['edit'])) $h .= '<a href="'.e($a['edit']).'" class="btn btn-sm btn-icon btn-text-secondary" title="Edit"><i class="ti ti-edit"></i></a>';
        if (! empty($a['delete'])) {
            $h .= '<form method="POST" action="'.e($a['delete']).'" class="d-inline" data-confirm="'.e($a['delete_msg'] ?? 'This cannot be undone.').'">'
                .'<input type="hidden" name="_token" value="'.csrf_token().'"><input type="hidden" name="_method" value="DELETE">'
                .'<button class="btn btn-sm btn-icon btn-text-danger" title="Delete"><i class="ti ti-trash"></i></button></form>';
        }
        return $h.'</div>';
    }

    /** A small POST button used for row actions (run now, fetch, etc.). */
    public static function post(string $url, string $label, string $icon = 'ti-player-play', string $class = 'btn-label-primary'): string
    {
        return '<form method="POST" action="'.e($url).'" class="d-inline"><input type="hidden" name="_token" value="'.csrf_token().'">'
            .'<button class="btn btn-sm '.e($class).'"><i class="ti '.e($icon).' me-1"></i>'.e($label).'</button></form>';
    }

    public static function date($d, string $fmt = 'd M Y, H:i'): string
    {
        return $d ? '<span class="text-nowrap small">'.e($d->format($fmt)).'</span>' : '<span class="text-muted">—</span>';
    }
}
