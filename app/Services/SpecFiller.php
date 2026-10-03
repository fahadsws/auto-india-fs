<?php

namespace App\Services;

use App\Models\VehicleModel;
use Illuminate\Support\Str;

/**
 * Makes sure a vehicle has a useful "Label: value" spec list.
 *  1. extract()   - reads spec tables / definition lists / "Label: value" lines straight from the page HTML (the article reader drops them).
 *  2. normalize() - flattens whatever the AI returned (nested objects, lists, "N/A") into clean Label => value pairs.
 *  3. fill()      - when a model still has too few specs: asks the AI to read them from the page text, and as a last resort to list the
 *                   official Indian-market specs it is confident about (never guessed). Existing values and a locked "specs" field are never touched.
 */
class SpecFiller
{
    /** Below this many specs a model counts as "thin" and gets filled. */
    public const ENOUGH = 8;
    private const MAX = 40;
    private const JUNK = '/^(n\/?a|na|-|—|–|\?|tbd|tba|not (available|applicable|specified|announced)|yet to be (announced|confirmed)|unknown|null|none|nil)$/i';

    /** The labels we ask the AI to use, so the public page shows consistent rows. */
    public const STANDARD = ['Engine', 'Displacement', 'Power', 'Torque', 'Transmission', 'Drive', 'Mileage (ARAI)', 'Fuel tank', 'Battery', 'Range', 'Top speed', 'Length', 'Width', 'Height', 'Wheelbase', 'Ground clearance', 'Boot space', 'Seating capacity', 'Kerb weight', 'Front brakes', 'Rear brakes', 'Front suspension', 'Rear suspension', 'Tyres', 'Airbags', 'Safety rating'];

    /* --------------------------- 1. read the page --------------------------- */

    /** @return array<string,string> */
    public static function extract(string $html): array
    {
        if (trim($html) === '') return [];
        $dom = PageFetcher::dom($html);
        $xp = new \DOMXPath($dom);
        $out = [];
        $add = function (string $k, string $v) use (&$out) {
            $k = self::label($k); $v = self::value($v);
            if ($k === '' || $v === '' || mb_strlen($k) > 45 || mb_strlen($v) > 140 || preg_match(self::JUNK, $v) || isset($out[$k])) return;
            if (! preg_match('/[a-z]/i', $k) || count($out) >= self::MAX * 2) return;
            $out[$k] = $v;
        };

        // table rows with two cells (th/td + td), also tables whose rows hold label,value pairs
        foreach ($xp->query('//tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $c) if (in_array($c->nodeName, ['td', 'th'], true)) $cells[] = trim(preg_replace('/\s+/u', ' ', $c->textContent));
            if (count($cells) === 2) $add($cells[0], $cells[1]);
            elseif (count($cells) === 4) { $add($cells[0], $cells[1]); $add($cells[2], $cells[3]); }
        }
        // definition lists
        foreach ($xp->query('//dl') as $dl) {
            $k = null;
            foreach ($dl->childNodes as $c) {
                if ($c->nodeName === 'dt') $k = $c->textContent;
                elseif ($c->nodeName === 'dd' && $k !== null) { $add($k, $c->textContent); $k = null; }
            }
        }
        // "Label: value" in list items, paragraphs and spans; and label/value sibling pairs inside list items
        foreach ($xp->query('//li | //p | //div[not(*)] | //span[not(*)]') as $n) {
            $t = trim(preg_replace('/\s+/u', ' ', $n->textContent));
            if ($t !== '' && mb_strlen($t) <= 160 && preg_match('/^([A-Za-z][A-Za-z0-9 \/&().\-]{1,40}?)\s*:\s*(\S.{0,130})$/u', $t, $m)) $add($m[1], $m[2]);
        }
        foreach ($xp->query('//li[count(*)=2] | //div[count(*)=2 and not(*/*/*)]') as $n) {
            $a = $n->firstChild; $b = $n->lastChild;
            while ($a && $a->nodeType !== XML_ELEMENT_NODE) $a = $a->nextSibling;
            while ($b && $b->nodeType !== XML_ELEMENT_NODE) $b = $b->previousSibling;
            if ($a && $b && $a !== $b) { $ka = trim($a->textContent); if (mb_strlen($ka) <= 40 && ! str_contains($ka, ':')) $add($ka, $b->textContent); }
        }
        // JSON-LD vehicle properties
        foreach (PageFetcher::jsonLd($dom) as $n) {
            foreach ((array) ($n['additionalProperty'] ?? []) as $p) if (is_array($p) && isset($p['name'], $p['value'])) $add((string) $p['name'], is_scalar($p['value']) ? (string) $p['value'] : '');
            foreach (['fuelType' => 'Fuel', 'vehicleTransmission' => 'Transmission', 'bodyType' => 'Body type', 'vehicleSeatingCapacity' => 'Seating capacity'] as $key => $label) {
                if (isset($n[$key]) && is_scalar($n[$key])) $add($label, (string) $n[$key]);
            }
        }
        return self::keepSpecLike($out);
    }

    /** Page-wide "Label: value" scraping also catches marketing lines; keep rows that look like specs (a unit/number, or a known label). */
    private static function keepSpecLike(array $rows): array
    {
        $known = '/(engine|displacement|power|torque|transmission|gearbox|drive|mileage|economy|efficiency|fuel|tank|battery|range|charging|speed|length|width|height|wheelbase|clearance|boot|luggage|seat|weight|brake|suspension|tyre|tire|wheel|airbag|safety|cc|bhp|ps|kw|nm|kmpl|km\/l|cylinder|emission|capacity|abs|gears?)/i';
        $unit = '/(\d|cc|bhp|ps|kw|nm|kmpl|mm|litre|ltr|petrol|diesel|cng|electric|hybrid|manual|automatic|amt|cvt|dct|disc|drum|abs|fwd|rwd|awd|4wd)/i';
        $out = [];
        foreach ($rows as $k => $v) if (preg_match($known, $k) || (preg_match($unit, $v) && mb_strlen($v) <= 60)) $out[$k] = $v;
        return array_slice($out, 0, self::MAX, true);
    }

    /* ------------------------------ 2. clean ------------------------------- */

    /** @return array<string,string> */
    public static function normalize(mixed $specs, string $prefix = ''): array
    {
        $out = [];
        $walk = function ($data, string $pre) use (&$walk, &$out) {
            if (is_string($data) && $pre === '') {   // "Label: value" lines
                foreach (preg_split('/\R/', $data) as $line) if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $walk([trim($k) => trim($v)], ''); }
                return;
            }
            foreach ((array) $data as $k => $v) {
                if (is_array($v) && isset($v['label'], $v['value'])) { $k = $v['label']; $v = $v['value']; }
                elseif (is_array($v) && isset($v['name'], $v['value'])) { $k = $v['name']; $v = $v['value']; }
                if (is_int($k) && is_string($v) && str_contains($v, ':')) { [$k, $v] = explode(':', $v, 2); }
                if (is_array($v)) { $walk($v, is_int($k) ? $pre : trim($pre.' '.self::label((string) $k))); continue; }
                if (is_int($k) || ! is_scalar($v)) continue;
                $label = self::label(trim($pre.' '.(string) $k)); $val = self::value((string) $v);
                if ($label === '' || $val === '' || preg_match(self::JUNK, $val) || mb_strlen($label) > 45) continue;
                if (! isset($out[$label])) $out[$label] = Str::limit($val, 140, '');
            }
        };
        $walk($specs, $prefix);
        return array_slice($out, 0, self::MAX, true);
    }

    private static function label(string $s): string
    {
        $s = AiClient::utf8($s);
        $s = self::tidy($s);
        return mb_strlen($s) > 1 ? Str::ucfirst($s) : '';
    }

    private static function value(string $s): string
    {
        return self::tidy($s);
    }

    /** Collapse whitespace and trim separator characters. Multi-byte safe: PHP's trim() with "–—•" would cut those characters' bytes in half. */
    private static function tidy(string $s): string
    {
        $s = AiClient::utf8($s);
        $s = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
        return trim(preg_replace('/^[\s:\-–—•*]+|[\s:\-–—•*]+$/u', '', $s) ?? '');
    }

    /** Add pairs from $extra whose label (case-insensitive) is not already present. */
    public static function merge(array $base, array $extra): array
    {
        $have = array_map(fn ($k) => mb_strtolower($k), array_keys($base));
        foreach ($extra as $k => $v) if (! in_array(mb_strtolower($k), $have, true)) { $base[$k] = $v; $have[] = mb_strtolower($k); }
        return array_slice($base, 0, self::MAX, true);
    }

    /* ------------------------------- 3. fill -------------------------------- */

    /**
     * Top the model's specs up to a useful size. Returns how many specs were added.
     * @param string $pageText readable text of the source page(s), if any
     */
    public static function fill(VehicleModel $model, string $pageText = '', bool $force = false): int
    {
        if ($model->isLocked('specs')) return 0;
        $have = self::normalize($model->specs ?? []);
        if (! $force && count($have) >= self::ENOUGH) return 0;
        if (! AiClient::configured()) return 0;

        $added = 0;
        if (trim($pageText) !== '') {
            $n = count($have); $have = self::merge($have, self::ask($model, $have, $pageText, false));
            $added += count($have) - $n;
        }
        if (count($have) < self::ENOUGH) {
            $n = count($have); $have = self::merge($have, self::ask($model, $have, '', true));   // official specs the AI is sure about
            $added += count($have) - $n;
        }
        if ($added) { $model->specs = $have; $model->save(); }
        return $added;
    }

    /** @return array<string,string> only the specs not already in $have */
    private static function ask(VehicleModel $model, array $have, string $pageText, bool $fromKnowledge): array
    {
        $name = trim(($model->brand ?? '').' '.$model->name);
        $type = config("vehicles.{$model->vehicle_type}.label", 'vehicle');
        $labels = implode(', ', self::STANDARD);
        $rule = $fromKnowledge
            ? "List the official manufacturer specifications of the Indian-market $name ($type) that you are CERTAIN about. Omit anything you are not sure of; never guess or round up. Do not include prices."
            : "Read the specifications of the $name ($type) from the PAGE TEXT. Use only what the text states.";
        $user = ($pageText !== '' ? "PAGE TEXT:\n".Str::limit($pageText, 12000, '')."\n\n" : '')
            .'ALREADY KNOWN (do not repeat): '.(json_encode($have, JSON_UNESCAPED_UNICODE) ?: '{}');

        $data = AiClient::json([
            ['role' => 'system', 'content' => "$rule Use these labels where they apply: $labels. Each value is short and carries its unit (e.g. \"1497 cc\", \"113 bhp @ 6000 rpm\", \"17.4 kmpl\"). A bike has no Boot space or Airbags unless stated; a truck may list Payload and GVW. Reply with ONE JSON object only: {\"specs\":{\"Label\":\"value\"}}. Return {\"specs\":{}} when nothing is known."],
            ['role' => 'user', 'content' => $user],
        ], ['temperature' => 0.1, 'max_tokens' => 1200, 'timeout' => 90]);

        return self::merge([], array_diff_key(self::normalize($data['specs'] ?? []), array_flip(array_keys($have))));
    }
}
