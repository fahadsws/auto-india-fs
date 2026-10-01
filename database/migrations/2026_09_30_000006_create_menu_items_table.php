<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('location', 10)->index();
            $t->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $t->string('title', 100);
            $t->string('url', 500)->nullable();
            $t->boolean('open_new_tab')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        $now = now();
        $add = function (string $loc, string $title, ?string $url, ?int $parent, int $sort) use ($now) {
            return DB::table('menu_items')->insertGetId([
                'location' => $loc, 'parent_id' => $parent, 'title' => $title, 'url' => $url,
                'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        foreach ([['Home', '/'], ['New cars', '/new-cars'], ['New bikes', '/new-bikes'], ['New trucks', '/new-trucks'],
                  ['Used cars', '/cars'], ['Compare', '/compare'], ['News', '/news'], ['Videos', '/videos']] as $i => [$t, $u]) {
            $add('header', $t, $u, null, $i);
        }

        $cols = [
            'Explore' => [['Used cars', '/cars'], ['New cars', '/new-cars'], ['New bikes', '/new-bikes'], ['New trucks', '/new-trucks'], ['Car news', '/news'], ['Videos', '/videos']],
            'Company' => [['About us', '/about'], ['Contact us', '/contact'], ['Sell your car', '/sell-your-car']],
            'Assistant' => [['Ask AI', '/assistant'], ['Search the site', '/search']],
        ];
        $n = 0;
        foreach ($cols as $head => $links) {
            $pid = $add('footer', $head, null, null, $n++);
            foreach ($links as $i => [$t, $u]) {
                $add('footer', $t, $u, $pid, $i);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
