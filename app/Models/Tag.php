<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Free-form label for expenses ("fixa", "delivery", "fim de semana"…). Names are unique ignoring
 * case; the colour is picked once from the name so a tag always looks the same.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 */
class Tag extends Model
{
    public const COLORS = ['blue', 'indigo', 'violet', 'pink', 'teal', 'amber', 'cyan', 'gray'];

    protected $fillable = ['name', 'slug', 'color'];

    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class);
    }

    public static function normalize(string $name): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', ltrim(trim($name), '#'))), 40, '');
    }

    /** Finds a tag by name (any case) or creates it. */
    public static function findOrCreateByName(string $name): self
    {
        $name = self::normalize($name);
        // "Fim de semana" and "fim-de-semana" are the same tag; accents are ignored too (diária = diaria).
        $slug = Str::slug($name) ?: mb_strtolower($name);

        return self::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'color' => self::COLORS[crc32($slug) % count(self::COLORS)]],
        );
    }
}
