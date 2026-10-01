<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ElevenLabs text-to-speech with the voice saved in Admin -> Settings. Never swaps voices silently:
 * a failure is returned as a clear error so the widget can say so instead of using another voice.
 */
class ElevenLabs
{
    public const DEFAULT_VOICE = '21m00Tcm4TlvDq8ikWAM';

    public static function configured(): bool
    {
        return (bool) Setting::get('elevenlabs.api_key');
    }

    public static function voiceId(): string
    {
        return trim((string) Setting::get('elevenlabs.voice_id', self::DEFAULT_VOICE)) ?: self::DEFAULT_VOICE;
    }

    public static function model(): string
    {
        return trim((string) Setting::get('elevenlabs.model', 'eleven_flash_v2_5')) ?: 'eleven_flash_v2_5';
    }

    private static function base(): string
    {
        return rtrim((string) config('services.elevenlabs.base'), '/');
    }

    private static function client()
    {
        return Http::withHeaders(['xi-api-key' => trim((string) Setting::get('elevenlabs.api_key'))])->timeout(30);
    }

    /** @return array{stability:float,similarity_boost:float,speed:float} */
    public static function voiceSettings(): array
    {
        $f = fn ($k, $d, $lo, $hi) => max($lo, min($hi, (float) Setting::get("elevenlabs.$k", $d)));
        return ['stability' => $f('stability', 0.5, 0, 1), 'similarity_boost' => $f('similarity', 0.75, 0, 1), 'speed' => $f('speed', 1.0, 0.7, 1.2)];
    }

    public static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/[*_`#]/', '', $text))));
    }

    private static function file(string $text): string
    {
        return 'tts/'.md5(json_encode([self::voiceId(), self::model(), self::voiceSettings(), $text])).'.mp3';
    }

    public static function isCached(string $text): bool
    {
        return Storage::disk('local')->exists(self::file($text));
    }

    /** @return array{ok:bool, body?:string, cached?:bool, status?:int, code?:string, message?:string} */
    public static function speak(string $text): array
    {
        $file = self::file($text);
        if (Storage::disk('local')->exists($file)) {
            return ['ok' => true, 'body' => Storage::disk('local')->get($file), 'cached' => true];
        }

        $vs = self::voiceSettings();
        try {
            $res = self::client()->withHeaders(['Accept' => 'audio/mpeg'])
                ->post(self::base().'/v1/text-to-speech/'.rawurlencode(self::voiceId()).'?output_format=mp3_44100_64', [
                    'text' => $text, 'model_id' => self::model(),
                    'voice_settings' => array_filter(['stability' => $vs['stability'], 'similarity_boost' => $vs['similarity_boost'], 'speed' => $vs['speed'] != 1.0 ? $vs['speed'] : null], fn ($v) => $v !== null),
                ]);
        } catch (\Throwable $e) {
            Log::warning('ElevenLabs request failed: '.$e->getMessage());
            return ['ok' => false, 'status' => 0, 'code' => 'unreachable', 'message' => 'Could not reach ElevenLabs.'];
        }

        if ($res->successful()) {
            Storage::disk('local')->put($file, $res->body());
            return ['ok' => true, 'body' => $res->body(), 'cached' => false];
        }

        $err = self::explain($res->status(), $res->json());
        Log::warning('ElevenLabs TTS failed: HTTP '.$res->status().' '.$err['code'].' voice='.self::voiceId().' model='.self::model().' - '.$err['message']);
        return ['ok' => false, 'status' => $res->status()] + $err;
    }

    /** Turn an ElevenLabs error body into a code + a message an admin can act on. */
    public static function explain(int $http, mixed $json): array
    {
        $detail = is_array($json) ? ($json['detail'] ?? null) : null;
        $code = is_array($detail) ? (string) ($detail['status'] ?? $detail['code'] ?? '') : '';
        $msg = is_array($detail) ? (string) ($detail['message'] ?? '') : (is_string($detail) ? $detail : '');
        $code = $code ?: ('http_'.$http);

        $hint = match (true) {
            $code === 'paid_plan_required' || $http === 402 => 'This is a Voice-Library voice and your plan cannot use it through the API. In ElevenLabs click "Add to my voices" (or pick a premade voice), then paste that voice ID here.',
            in_array($code, ['voice_not_found', 'voice_does_not_exist'], true) || $http === 404 => 'This voice ID was not found for the account that owns the API key. Copy the ID from My Voices.',
            $http === 401 || in_array($code, ['invalid_api_key', 'needs_authorization'], true) => 'The API key is invalid or has no Text-to-Speech permission. Create a new key with "Text to Speech" enabled.',
            in_array($code, ['quota_exceeded'], true) || $http === 429 => 'The ElevenLabs character quota (or rate limit) is used up.',
            default => '',
        };

        return ['code' => $code, 'message' => trim(($msg ? $msg.' ' : '').$hint) ?: 'ElevenLabs returned HTTP '.$http.'.'];
    }

    /** Setup check used by Admin -> Settings: key, plan, voice and a real sample in the saved voice. */
    public static function diagnose(): array
    {
        $rows = [];
        if (! self::configured()) {
            return [['ElevenLabs API key', 'warn', 'No key saved. Without a key the assistant uses the browser voice.']];
        }
        $rows[] = ['ElevenLabs API key', 'ok', 'Key saved.'];

        try {
            $u = self::client()->get(self::base().'/v1/user/subscription');
            if ($u->successful()) {
                $j = $u->json();
                $rows[] = ['ElevenLabs plan', 'ok', ucfirst((string) ($j['tier'] ?? 'unknown')).' plan - '.number_format((int) ($j['character_count'] ?? 0)).' of '.number_format((int) ($j['character_limit'] ?? 0)).' characters used this period.'];
            } else {
                $rows[] = ['ElevenLabs plan', 'warn', 'Could not read plan/quota (the key may not have the "User" permission - that is fine for speech).'];
            }

            $v = self::client()->get(self::base().'/v1/voices/'.rawurlencode(self::voiceId()));
            if ($v->successful()) {
                $j = $v->json();
                $rows[] = ['Voice ID', 'ok', ($j['name'] ?? 'Voice').' ('.($j['category'] ?? 'voice').') - '.self::voiceId()];
            } elseif ($v->status() === 401) {
                $rows[] = ['Voice ID', 'warn', 'Key cannot list voices (missing "Voices: read" permission). The speech test below shows whether the voice works.'];
            } else {
                $e = self::explain($v->status(), $v->json());
                $rows[] = ['Voice ID', 'fail', self::voiceId().' - '.$e['message']];
            }
        } catch (\Throwable $e) {
            $rows[] = ['ElevenLabs connection', 'fail', 'Could not reach ElevenLabs: '.$e->getMessage()];
            return $rows;
        }

        $sample = self::speak('Hello! This is how your assistant will sound.');
        $rows[] = $sample['ok']
            ? ['Speech test', 'ok', 'Spoken with voice '.self::voiceId().' / model '.self::model().'.', 'data:audio/mpeg;base64,'.base64_encode($sample['body'])]
            : ['Speech test', 'fail', $sample['message'] ?? 'Failed.'];

        return $rows;
    }
}
