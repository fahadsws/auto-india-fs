<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Downloads a remote image into our own storage (public/uploads/{dir}/{Y}/{m}) and returns its public URL. */
class ImageStore
{
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];

    public static function fromUrl(?string $url, string $dir = 'articles'): ?string
    {
        return self::fetch($url, $dir)['path'] ?? null;
    }

    /**
     * @param  array<int,string>  $knownHashes  md5s already stored; a matching image is skipped (returns null)
     * @return array{path:string,hash:string}|null
     */
    public static function fetch(?string $url, string $dir = 'articles', array $knownHashes = []): ?array
    {
        if (! $url || ! preg_match('#^https?://#i', $url)) return null;
        try {
            $res = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; '.Robots::AGENT.'/1.0)'])->get($url);
            if (! $res->successful()) return null;
            $type = strtolower(trim(explode(';', $res->header('Content-Type') ?? '')[0]));
            $body = $res->body();
            if (! isset(self::EXT[$type]) || strlen($body) < 6000 || strlen($body) > 6 * 1024 * 1024) return null;
            $hash = md5($body);
            if (in_array($hash, $knownHashes, true)) return null;
            // Saved under public/uploads/{dir}/{Y}/{m}/ like every admin upload; the public URL is what gets stored.
            $rel = 'uploads/'.trim($dir, '/').'/'.date('Y/m');
            $folder = public_path($rel);
            if (! is_dir($folder) && ! @mkdir($folder, 0755, true) && ! is_dir($folder)) return null;
            $name = Str::random(24).'.'.self::EXT[$type];
            if (file_put_contents($folder.DIRECTORY_SEPARATOR.$name, $body) === false) return null;
            return ['path' => '/'.$rel.'/'.$name, 'hash' => $hash];   // host-independent; models resolve it with asset()
        } catch (\Throwable) {
            return null;
        }
    }
}
