<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'locale',
    ];

    /**
     * @return array<string, array{code: string, name: string, locale: string}>
     */
    public static function map(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = self::query()
                ->get(['code', 'name', 'locale'])
                ->keyBy('code')
                ->map(fn (Currency $currency) => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'locale' => $currency->locale,
                ])
                ->toArray();
        }

        return $cache;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::map());
    }

    public static function localeFor(string $code, string $fallback = 'es-CL'): string
    {
        $map = self::map();

        return $map[$code]['locale'] ?? $fallback;
    }
}
