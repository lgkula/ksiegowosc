<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Klucze przechowywane w postaci zaszyfrowanej. */
    public const SECRET_KEYS = ['ksef_token'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return in_array($key, self::SECRET_KEYS, true) ? Crypt::decryptString($value) : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        if ($value !== null && in_array($key, self::SECRET_KEYS, true)) {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
