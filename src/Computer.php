<?php

declare(strict_types=1);

namespace Othello;

use LogicException;

/** Small, deterministic opponent: prefer corners, then immediate captures. */
final class Computer
{
    public function choose(Board $board, string $player): string
    {
        $bestMove = null;
        $bestScore = -1;
        foreach ($board->legalMoves($player) as $move) {
            [$x, $y] = Board::parseCoordinate($move);
            $score = count($board->flipsAt($x, $y, $player));
            if (in_array($move, ['a1', 'h1', 'a8', 'h8'], true)) {
                $score += 100;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $move;
            }
        }
        if ($bestMove === null) {
            throw new LogicException('合法手がありません。');
        }
        return $bestMove;
    }
}
