<?php

declare(strict_types=1);

namespace OthelloDemo\Baseline;

/** Intentionally nested reference implementation, used only for comparison.
 * Input contract matches a validated Board: 8x8 B/W/. rows and player B or W.
 */
final class MoveFinder
{
    /** @param list<string> $rows @return list<string> */
    public static function legalMoves(array $rows, string $player): array
    {
        $opponent = $player === 'B' ? 'W' : 'B';
        $moves = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                if ($rows[$y][$x] === '.') {
                    $legal = false;
                    for ($dy = -1; $dy <= 1; $dy++) {
                        for ($dx = -1; $dx <= 1; $dx++) {
                            if ($dx !== 0 || $dy !== 0) {
                                $nx = $x + $dx;
                                $ny = $y + $dy;
                                $seenOpponent = false;
                                while ($nx >= 0 && $nx < 8 && $ny >= 0 && $ny < 8) {
                                    if ($rows[$ny][$nx] === $opponent) {
                                        $seenOpponent = true;
                                        $nx += $dx;
                                        $ny += $dy;
                                    } else {
                                        if ($seenOpponent && $rows[$ny][$nx] === $player) {
                                            $legal = true;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if ($legal) {
                        $moves[] = chr(ord('a') + $x) . ($y + 1);
                    }
                }
            }
        }
        return $moves;
    }
}
