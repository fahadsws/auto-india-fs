<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'is_secret'];

    /** Keys whose values are stored encrypted. */
    public const SECRETS = ['ai.api_key', 'elevenlabs.api_key', 'youtube.api_key', 'cron.token'];

    private static function all_(): array
    {
        return Cache::rememberForever('settings.all', function () {
            $out = [];
            foreach (static::query()->get() as $row) {
                $v = $row->value;
                if ($row->is_secret && $v !== null && $v !== '') {
                    try { $v = Crypt::decryptString($v); } catch (\Throwable) { $v = null; }
                }
                $out[$row->key] = $v;
            }
            return $out;
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all_();
        $v = $all[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = static::get($key);
        return $v === null ? $default : in_array((string) $v, ['1', 'true', 'on', 'yes'], true);
    }

    public static function put(string $key, mixed $value): void
    {
        $secret = in_array($key, self::SECRETS, true);
        $stored = ($secret && $value !== null && $value !== '') ? Crypt::encryptString((string) $value) : $value;
        static::updateOrCreate(['key' => $key], ['value' => $stored, 'is_secret' => $secret]);
        Cache::forget('settings.all');
    }
}
