<?php

declare(strict_types=1);

namespace Othello;

use InvalidArgumentException;

final class Console
{
    public function run(array $arguments): int
    {
        $unknown = array_diff($arguments, ['--help', '-h', '--demo', '--two-player']);
        if ($unknown !== []) {
            fwrite(STDERR, '不明なオプション: ' . implode(' ', $unknown) . PHP_EOL);
            return 2;
        }
        if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
            $this->help();
            return 0;
        }
        $demo = in_array('--demo', $arguments, true);
        $twoPlayer = in_array('--two-player', $arguments, true);
        if ($demo && $twoPlayer) {
            fwrite(STDERR, "--demo と --two-player は同時に指定できません。\n");
            return 2;
        }
        return $this->playGame($demo, $twoPlayer);
    }

    private function playGame(bool $demo, bool $twoPlayer): int
    {
        $game = new Game();
        $computer = new Computer();
        echo "\n  OTHELLO / CCCC DEMO\n";
        echo "  B = 黒   W = 白   * = 置けるマス\n";
        echo "  a1〜h8で着手 / helpで説明 / qで終了\n\n";
        while (!$game->isOver()) {
            $this->render($game);
            $player = $game->currentPlayer();
            $automated = $demo || (!$twoPlayer && $player === Board::WHITE);
            $move = $automated ? $computer->choose($game->board(), $player) : $this->readMove($player);
            if ($move === null) {
                echo "\nゲームを終了しました。\n";
                return 0;
            }
            $this->applyMove($game, $move);
        }
        $this->render($game);
        echo '対局終了: ' . $game->result() . PHP_EOL;
        return 0;
    }

    private function applyMove(Game $game, string $move): void
    {
        try {
            $player = $game->currentPlayer();
            $flipped = $game->play($move);
            echo "  $player > $move ($flipped 枚反転)\n\n";
            if ($game->passedPlayer() !== null) {
                echo '  ' . $game->passedPlayer() . " は合法手がないため自動パス。\n\n";
            }
        } catch (InvalidArgumentException $error) {
            echo '  ' . $error->getMessage() . "\n\n";
        }
    }

    private function readMove(string $player): ?string
    {
        while (true) {
            echo "  $player の手 > ";
            $line = fgets(STDIN);
            if ($line === false) {
                return null;
            }
            $move = strtolower(trim($line));
            if (in_array($move, ['q', 'quit', 'exit'], true)) {
                return null;
            }
            if ($move === 'help') {
                $this->help();
                continue;
            }
            return $move;
        }
    }

    private function render(Game $game): void
    {
        $moves = array_fill_keys($game->board()->legalMoves($game->currentPlayer()), true);
        echo "     a b c d e f g h\n   +-----------------+\n";
        foreach ($game->board()->rows() as $y => $row) {
            echo ' ' . ($y + 1) . ' | ';
            for ($x = 0; $x < Board::SIZE; $x++) {
                $coordinate = Board::coordinate($x, $y);
                echo (isset($moves[$coordinate]) ? '*' : $row[$x]) . ' ';
            }
            echo '| ' . ($y + 1) . PHP_EOL;
        }
        $score = $game->board()->score();
        echo "   +-----------------+\n";
        echo "   黒 B: {$score['B']}  白 W: {$score['W']}  空き: {$score['empty']}\n\n";
    }

    private function help(): void
    {
        echo "php bin/othello.php [--two-player | --demo]\n";
        echo "  通常: あなたが黒、CPUが白。--two-player: 2人対戦。\n";
        echo "  --demo: CPU同士で最後まで自動対戦（毎回同じ棋譜）。\n";
        echo "  入力例: d3 / C4。相手の石を挟むマスに置けます。\n";
        echo "  置けないときは自動パス。双方置けなくなったら石の多い側が勝ち。\n";
    }
}
