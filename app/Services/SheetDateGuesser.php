<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Guesses a production date from a sheet's tab name, mirroring
 * guessDateFromSheetName() in production-import.tsx. Handles spaced
 * ("JULY 6"), separated ("July-6", "July_6", "July.6"), and squashed
 * ("JULY11", "July6") tab names.
 *
 * Used by ImportFileWatcher so unattended imports can figure out which
 * date each sheet belongs to without a person picking it in the UI.
 * Returns null when nothing recognizable is found — the watcher skips
 * that sheet rather than guessing wrong and silently overwriting the
 * wrong day's data.
 */
class SheetDateGuesser
{
    protected const MONTH_NAMES = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];

    public static function guess(string $sheetName): ?string
    {
        if (! preg_match('/([A-Za-z]{3,9})[\s\-_.]*(\d{1,2})(?!\d)/', trim($sheetName), $m)) {
            return null;
        }

        [, $monthPart, $dayPart] = $m;
        $monthLower = strtolower($monthPart);

        $monthIndex = array_search($monthLower, self::MONTH_NAMES, true);
        if ($monthIndex === false) {
            $monthIndex = array_search(
                substr($monthLower, 0, 3),
                array_map(fn ($m) => substr($m, 0, 3), self::MONTH_NAMES),
                true,
            );
        }
        if ($monthIndex === false) {
            return null;
        }

        $day = (int) $dayPart;
        if ($day < 1 || $day > 31) {
            return null;
        }

        try {
            return Carbon::create(now()->year, $monthIndex + 1, $day)->toDateString();
        } catch (\Throwable) {
            // e.g. "February 30" — not a real date
            return null;
        }
    }
}