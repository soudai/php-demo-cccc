<?php

declare(strict_types=1);

namespace Othello;

use InvalidArgumentException;

final class Board
{
    public const SIZE = 8;
    public const EMPTY = '.';
    public const BLACK = 'B';
    public const WHITE = 'W';
    private const DIRECTIONS = [
        [-1, -1], [0, -1], [1, -1], [-1, 0],
        [1, 0], [-1, 1], [0, 1], [1, 1],
    ];

    /** @param list<string> $rows Eight rows using B, W and . */
    public function __construct(private array $rows = [
        '........', '........', '........', '...WB...',
        '...BW...', '........', '........', '........',
    ]) {
        if (count($rows) !== self::SIZE || !array_is_list($rows)) {
            throw new InvalidArgumentException('盤面は8行で指定してください。');
        }
        foreach ($rows as $row) {
            if (!is_string($row) || preg_match('/^[.BW]{8}$/D', $row) !== 1) {
                throw new InvalidArgumentException('各行は B / W / . の8文字です。');
            }
        }
    }

    public static function opponent(string $player): string
    {
        return match ($player) {
            self::BLACK => self::WHITE,
            self::WHITE => self::BLACK,
            default => throw new InvalidArgumentException('石はBまたはWです。'),
        };
    }

    public static function inside(int $x, int $y): bool
    {
        return $x >= 0 && $x < self::SIZE && $y >= 0 && $y < self::SIZE;
    }

    /** @return array{int, int} */
    public static function parseCoordinate(string $coordinate): array
    {
        $coordinate = strtolower(trim($coordinate));
        if (preg_match('/^[a-h][1-8]$/D', $coordinate) !== 1) {
            throw new InvalidArgumentException('a1〜h8の座標を入力してください。');
        }
        return [ord($coordinate[0]) - ord('a'), (int) $coordinate[1] - 1];
    }

    public static function coordinate(int $x, int $y): string
    {
        if (!self::inside($x, $y)) {
            throw new InvalidArgumentException('盤面の範囲外です。');
        }
        return chr(ord('a') + $x) . ($y + 1);
    }

    /** @return list<string> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return list<string> Row-major order, shared with the baseline. */
    public function legalMoves(string $player): array
    {
        self::opponent($player);
        $moves = [];
        for ($index = 0; $index < self::SIZE * self::SIZE; $index++) {
            $x = $index % self::SIZE;
            $y = intdiv($index, self::SIZE);
            if ($this->flipsAt($x, $y, $player) !== []) {
                $moves[] = self::coordinate($x, $y);
            }
        }
        return $moves;
    }

    /** @return list<array{int, int}> */
    public function flipsAt(int $x, int $y, string $player): array
    {
        $opponent = self::opponent($player);
        if (!self::inside($x, $y)) {
            return [];
        }
        if ($this->rows[$y][$x] !== self::EMPTY) {
            return [];
        }
        $flips = [];
        foreach (self::DIRECTIONS as [$dx, $dy]) {
            array_push($flips, ...$this->scanDirection($x, $y, $dx, $dy, $opponent));
        }
        return $flips;
    }

    /** Scan a contiguous opponent run; retain it only when closed by our stone.
     * @return list<array{int, int}>
     */
    private function scanDirection(int $x, int $y, int $dx, int $dy, string $opponent): array
    {
        $line = [];
        $x += $dx;
        $y += $dy;
        while (self::inside($x, $y) && $this->rows[$y][$x] === $opponent) {
            $line[] = [$x, $y];
            $x += $dx;
            $y += $dy;
        }
        if (!self::inside($x, $y)) {
            return [];
        }
        if ($this->rows[$y][$x] !== self::opponent($opponent)) {
            return [];
        }
        return $line;
    }

    public function play(string $coordinate, string $player): int
    {
        [$x, $y] = self::parseCoordinate($coordinate);
        $flips = $this->flipsAt($x, $y, $player);
        if ($flips === []) {
            throw new InvalidArgumentException('そのマスには置けません。* のマスを選んでください。');
        }
        $this->rows[$y][$x] = $player;
        foreach ($flips as [$flipX, $flipY]) {
            $this->rows[$flipY][$flipX] = $player;
        }
        return count($flips);
    }

    /** @return array{B: int, W: int, empty: int} */
    public function score(): array
    {
        $cells = implode('', $this->rows);
        return [
            'B' => substr_count($cells, self::BLACK),
            'W' => substr_count($cells, self::WHITE),
            'empty' => substr_count($cells, self::EMPTY),
        ];
    }
}
