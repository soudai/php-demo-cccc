<?php

declare(strict_types=1);

namespace Othello;

use LogicException;

final class Game
{
    private ?string $passedPlayer = null;

    public function __construct(
        private Board $board = new Board(),
        private string $currentPlayer = Board::BLACK,
    ) {
        Board::opponent($currentPlayer);
        $this->skipBlockedPlayer();
    }

    public function board(): Board
    {
        return $this->board;
    }

    public function currentPlayer(): string
    {
        return $this->currentPlayer;
    }

    public function passedPlayer(): ?string
    {
        return $this->passedPlayer;
    }

    public function isOver(): bool
    {
        return $this->board->legalMoves(Board::BLACK) === []
            && $this->board->legalMoves(Board::WHITE) === [];
    }

    public function play(string $coordinate): int
    {
        if ($this->isOver()) {
            throw new LogicException('ゲームは終了しています。');
        }
        $flipped = $this->board->play($coordinate, $this->currentPlayer);
        $this->currentPlayer = Board::opponent($this->currentPlayer);
        $this->passedPlayer = null;
        $this->skipBlockedPlayer();
        return $flipped;
    }

    private function skipBlockedPlayer(): void
    {
        if ($this->isOver()) {
            return;
        }
        if ($this->board->legalMoves($this->currentPlayer) === []) {
            $this->passedPlayer = $this->currentPlayer;
            $this->currentPlayer = Board::opponent($this->currentPlayer);
        }
    }

    public function result(): string
    {
        $score = $this->board->score();
        if ($score['B'] === $score['W']) {
            return '引き分け';
        }
        return $score['B'] > $score['W'] ? '黒 (B) の勝ち' : '白 (W) の勝ち';
    }
}
