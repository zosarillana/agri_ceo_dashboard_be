<?php

namespace App\Models;  // <-- ADD THIS LINE AT THE TOP

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'header_row', 'signature', 'column_map'];

    protected $casts = [
        'signature' => 'array',
        'column_map' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * Does this template match a sheet whose header row contains the given
     * labels? We normalize (trim + lowercase) and require every label in
     * this template's signature to be present — extra columns in the new
     * sheet are fine, they're just ignored on import.
     */
    public function matches(array $headerLabels): bool
    {
        $incoming = collect($headerLabels)
            ->map(fn ($label) => trim(strtolower((string) $label)))
            ->filter()
            ->values();

        $required = collect($this->signature)
            ->map(fn ($label) => trim(strtolower((string) $label)));

        return $required->every(fn ($label) => $incoming->contains($label));
    }

    /**
     * Find the first saved template whose signature matches the given
     * header labels, if any. Checked most-recently-updated first so a
     * refined template takes precedence over an older, looser one.
     */
    public static function findMatch(array $headerLabels): ?self
    {
        return static::query()
            ->orderByDesc('updated_at')
            ->get()
            ->first(fn (ImportTemplate $template) => $template->matches($headerLabels));
    }
}