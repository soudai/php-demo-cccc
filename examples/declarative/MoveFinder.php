<?php

declare(strict_types=1);

namespace OthelloDemo\Declarative;

/**
 * 宣言的・関数型スタイルで合法手を求める比較用実装。
 *
 * 入力は検証済みの 8×8 の盤面（B/W/.）と手番（B/W）を前提とする。
 */
final class MoveFinder
{
    private const SIZE = 8;
    private const EMPTY = '.';
    private const DIRECTIONS = [
        [-1, -1], [0, -1], [1, -1],
        [-1, 0],           [1, 0],
        [-1, 1],  [0, 1],  [1, 1],
    ];

    /**
     * @param list<string> $rows
     * @return list<string>
     */
    public static function legalMoves(array $rows, string $player): array
    {
        $opponent = self::opponent($player);
        $positions = self::positions();

        $legal = array_filter(
            $positions,
            static fn (array $position): bool => self::isLegalAt($rows, $player, $opponent, $position[0], $position[1])
        );

        return array_values(array_map(
            static fn (array $position): string => self::coordinate($position[0], $position[1]),
            $legal
        ));
    }

    /** @return list<array{int, int}> */
    private static function positions(): array
    {
        return array_map(
            static fn (int $index): array => [$index % self::SIZE, intdiv($index, self::SIZE)],
            range(0, self::SIZE * self::SIZE - 1)
        );
    }

    private static function isLegalAt(array $rows, string $player, string $opponent, int $x, int $y): bool
    {
        if ($rows[$y][$x] !== self::EMPTY) {
            return false;
        }

        return self::any(
            self::DIRECTIONS,
            static fn (array $direction): bool => self::canCaptureInDirection(
                $rows,
                $player,
                $opponent,
                $x,
                $y,
                $direction[0],
                $direction[1]
            )
        );
    }

    /**
     * @template T
     * @param list<T> $items
     * @param callable(T): bool $predicate
     */
    private static function any(array $items, callable $predicate): bool
    {
        foreach ($items as $item) {
            if ($predicate($item)) {
                return true;
            }
        }
        return false;
    }

    private static function canCaptureInDirection(array $rows, string $player, string $opponent, int $x, int $y, int $dx, int $dy): bool
    {
        $seenOpponent = false;
        $x += $dx;
        $y += $dy;

        while (self::inside($x, $y) && $rows[$y][$x] === $opponent) {
            $seenOpponent = true;
            $x += $dx;
            $y += $dy;
        }

        return $seenOpponent
            && self::inside($x, $y)
            && $rows[$y][$x] === $player;
    }

    private static function inside(int $x, int $y): bool
    {
        return $x >= 0
            && $x < self::SIZE
            && $y >= 0
            && $y < self::SIZE;
    }

    private static function opponent(string $player): string
    {
        return $player === 'B' ? 'W' : 'B';
    }

    private static function coordinate(int $x, int $y): string
    {
        return chr(ord('a') + $x) . ($y + 1);
    }
}
