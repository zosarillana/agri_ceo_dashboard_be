<?php

namespace App\Services;

/**
 * Guesses which row in a sheet is the real header row, mirroring
 * guessHeaderRow() in production-import.tsx: within the first 20 rows,
 * pick the one with the most non-empty, non-numeric-looking cells — title
 * blocks and date rows above the real table tend to have few, mostly
 * numeric or mostly empty cells by comparison.
 *
 * Used by ImportFileWatcher so unattended imports can find the header row
 * on their own, the same way a person would when clicking through the
 * grid preview in the UI.
 */
class HeaderRowGuesser
{
    /**
     * @param  array<int, array<int, mixed>>  $rows  0-indexed rows of 0-indexed cell values
     * @return int 0-indexed row number
     */
    public static function guess(array $rows): int
    {
        $bestIndex = 0;
        $bestScore = -1;
        $depth = min(count($rows), 20);

        for ($i = 0; $i < $depth; $i++) {
            $row = $rows[$i] ?? [];
            $nonEmpty = array_filter($row, fn ($c) => trim((string) $c) !== '');
            if (count($nonEmpty) < 2) {
                continue;   
            }
            $textLike = array_filter($nonEmpty, fn ($c) => ! self::looksNumeric($c));
            $score = count($nonEmpty) + count($textLike) * 2;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    protected static function looksNumeric($value): bool
    {
        $s = trim((string) $value);
        if ($s === '') {
            return false;
        }
        return is_numeric(str_replace(',', '', $s));
    }
}