<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/examples/baseline/MoveFinder.php';
require dirname(__DIR__) . '/examples/declarative/MoveFinder.php';

use Othello\Board;
use Othello\Computer;
use Othello\Game;
use OthelloDemo\Baseline\MoveFinder;
use OthelloDemo\Declarative\MoveFinder as DeclarativeMoveFinder;

$tests = 0;
$assertions = 0;
$positions = 0;

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function rejects(callable $action, string $exception = InvalidArgumentException::class): void
{
    try {
        $action();
    } catch (Throwable $error) {
        same(true, $error instanceof $exception, $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected exception: ' . $exception);
}

function test(string $name, callable $action): void
{
    global $tests;
    $action();
    $tests++;
    echo "PASS $name\n";
}

/** @return array{int, string, string} */
function cli(array $arguments, string $input = ''): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/othello.php', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHP.');
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}

try {
    test('opening legal moves, score and turn', static function (): void {
        $board = new Board();
        same(['d3', 'c4', 'f5', 'e6'], $board->legalMoves('B'));
        same(['e3', 'f4', 'c5', 'd6'], $board->legalMoves('W'));
        same(['B' => 2, 'W' => 2, 'empty' => 60], $board->score());
        $game = new Game($board);
        same('B', $game->currentPlayer());
        same(1, $game->play(' D3 '));
        same(['B' => 4, 'W' => 1, 'empty' => 59], $board->score());
        same('W', $game->currentPlayer());
        same(null, $game->passedPlayer());
    });

    test('coordinates and invalid input leave the board unchanged', static function (): void {
        $board = new Board();
        $before = $board->rows();
        foreach (['a0', 'i1', 'd33', '', 'pass', 'a1', 'd4'] as $move) {
            rejects(static fn () => $board->play($move, 'B'));
            same($before, $board->rows());
        }
        same([7, 7], Board::parseCoordinate('H8'));
        same('a1', Board::coordinate(0, 0));
        rejects(static fn () => Board::coordinate(8, 0));
        rejects(static fn () => $board->legalMoves('X'));
        rejects(static fn () => new Board(['........']));
        rejects(static fn () => new Board(array_fill(0, 8, '........X')));
        rejects(static fn () => new Board(array_fill(0, 8, '.......X')));
        same([], $board->flipsAt(-1, 0, 'B'));
        same([], $board->flipsAt(8, 7, 'B'));
    });

    test('captures in all eight directions at once', static function (): void {
        $board = new Board([
            '........', '.B.B.B..', '..WWW...', '.BW.WB..',
            '..WWW...', '.B.B.B..', '........', '........',
        ]);
        same(8, count($board->flipsAt(3, 3, 'B')));
        same(8, $board->play('d4', 'B'));
        same(['B' => 17, 'W' => 0, 'empty' => 47], $board->score());
    });

    test('open runs and edges cannot capture or wrap around', static function (): void {
        $board = new Board([
            '.WW.....', '........', '........', '........',
            '........', '........', '.......W', 'B.......',
        ]);
        same([], $board->flipsAt(0, 0, 'B'));
        same([], $board->flipsAt(6, 6, 'B'));
        $edge = new Board(['.WWWWWWB', ...array_fill(0, 7, '........')]);
        same(6, $edge->play('a1', 'B'));
        same('BBBBBBBB', $edge->rows()[0]);
    });

    test('forced pass retains the playable turn and then ends the game', static function (): void {
        $board = new Board(['.WBBBBBB', ...array_fill(0, 6, 'BBBBBBBB'), '.WBBBBBB']);
        $game = new Game($board, 'W');
        same('W', $game->passedPlayer());
        same('B', $game->currentPlayer());
        $game->play('a1');
        same('W', $game->passedPlayer());
        same('B', $game->currentPlayer());
        same(false, $game->isOver());
        $game->play('a8');
        same(true, $game->isOver());
        same('黒 (B) の勝ち', $game->result());
        same(['B' => 64, 'W' => 0, 'empty' => 0], $board->score());
        rejects(static fn () => $game->play('a1'), LogicException::class);
    });

    test('game may end with empty cells; a full board can be a draw', static function (): void {
        $blocked = new Game(new Board(['B.......', ...array_fill(0, 7, '........')]));
        same(true, $blocked->isOver());
        same(63, $blocked->board()->score()['empty']);
        $draw = new Game(new Board(array_fill(0, 8, 'BWBWBWBW')));
        same(true, $draw->isOver());
        same('引き分け', $draw->result());
        $white = new Game(new Board(array_fill(0, 8, 'WWWWWWWW')));
        same('白 (W) の勝ち', $white->result());
    });

    test('computer prefers corners and rejects positions with no move', static function (): void {
        $computer = new Computer();
        $board = new Board(['.WB.....', '........', '.WWB....', ...array_fill(0, 5, '........')]);
        same('a1', $computer->choose($board, 'B'));
        same('d3', $computer->choose(new Board(), 'B'));
        rejects(static fn () => $computer->choose(new Board(array_fill(0, 8, 'BBBBBBBB')), 'W'), LogicException::class);
    });

    test('declarative legal moves require an empty origin in each of eight directions', static function (): void {
        $directions = [[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]];
        foreach (['B' => 'W', 'W' => 'B'] as $player => $opponent) {
            foreach ($directions as [$dx, $dy]) {
                $rows = array_fill(0, 8, '........');
                $rows[3 + $dy][3 + $dx] = $opponent;
                $rows[3 + 2 * $dy][3 + 2 * $dx] = $player;
                same(['d4'], DeclarativeMoveFinder::legalMoves($rows, $player), "player=$player direction=$dx,$dy");
                $rows[3][3] = $player;
                same([], DeclarativeMoveFinder::legalMoves($rows, $player), 'An occupied origin cannot be a legal move.');
            }
        }
    });

    test('declarative legal moves reject incomplete brackets and board wrapping', static function (): void {
        $cases = [
            'empty board' => array_fill(0, 8, '........'),
            'no adjacent opponent' => ['.B......', ...array_fill(0, 7, '........')],
            'opponent run ends at an empty cell' => ['.WW.....', ...array_fill(0, 7, '........')],
            'opponent run leaves the right edge' => ['.WWWWWWW', ...array_fill(0, 7, '........')],
            'opponent run leaves the left edge' => ['WWWWWWW.', ...array_fill(0, 7, '........')],
            'opponent run leaves the top edge' => ['W.......', '.W......', '........', ...array_fill(0, 5, '........')],
            'opponent run leaves the bottom edge' => [...array_fill(0, 6, '........'), '......W.', '.......W'],
            'empty gap before own stone' => ['.W.B....', ...array_fill(0, 7, '........')],
            'last column does not wrap to next row' => ['......BW', ...array_fill(0, 7, '........')],
            'first column does not wrap to previous row' => ['........', 'WB......', ...array_fill(0, 6, '........')],
        ];
        foreach ($cases as $name => $rows) {
            same([], DeclarativeMoveFinder::legalMoves($rows, 'B'), $name);
            $swapped = array_map(static fn (string $row): string => strtr($row, ['B' => 'W', 'W' => 'B']), $rows);
            same([], DeclarativeMoveFinder::legalMoves($swapped, 'W'), "$name with swapped colors");
        }
    });

    test('declarative legal moves preserve long brackets and dense row-major order', static function (): void {
        $cases = [
            [['.WWWWWWB', ...array_fill(0, 7, '........')], ['a1']],
            [['BWWWWWW.', ...array_fill(0, 7, '........')], ['h1']],
            [['.WBBBBW.', ...array_fill(0, 6, '........'), '.WBBBBW.'], ['a1', 'h1', 'a8', 'h8']],
            [[
                'BBBBBBBB', 'BBBBBBBB', 'BBWWWBBB', 'BBW.WBBB',
                'BBWWWBBB', 'BBBBBBBB', 'BBBBBBBB', 'BBBBBBBB',
            ], ['d4']],
        ];
        foreach ($cases as [$rows, $expected]) {
            same($expected, DeclarativeMoveFinder::legalMoves($rows, 'B'));
            $swapped = array_map(static fn (string $row): string => strtr($row, ['B' => 'W', 'W' => 'B']), $rows);
            same($expected, DeclarativeMoveFinder::legalMoves($swapped, 'W'));
        }
    });

    test('baseline equivalence and stone invariants over 20 seeded complete games', static function (): void {
        global $positions;
        for ($seed = 1; $seed <= 20; $seed++) {
            mt_srand($seed);
            $game = new Game();
            $turns = 0;
            while (true) {
                $board = $game->board();
                foreach (['B', 'W'] as $player) {
                    same(MoveFinder::legalMoves($board->rows(), $player), $board->legalMoves($player), "seed=$seed turn=$turns player=$player");
                    same(DeclarativeMoveFinder::legalMoves($board->rows(), $player), $board->legalMoves($player), "declarative seed=$seed turn=$turns player=$player");
                }
                $positions++;
                if ($game->isOver()) {
                    break;
                }
                $player = $game->currentPlayer();
                $opponent = Board::opponent($player);
                $moves = $board->legalMoves($player);
                same(true, $moves !== []);
                $before = $board->score();
                $flips = $game->play($moves[mt_rand(0, count($moves) - 1)]);
                $after = $board->score();
                same($before['empty'] - 1, $after['empty']);
                same($before[$player] + $flips + 1, $after[$player]);
                same($before[$opponent] - $flips, $after[$opponent]);
                same(64, array_sum($after));
                same(true, ++$turns <= 60);
            }
        }
    });

    test('CLI help, errors, EOF, invalid moves and two-player interaction', static function (): void {
        [$code, $out, $err] = cli(['--help']);
        same(0, $code);
        same(true, str_contains($out, '--two-player'));
        same('', $err);
        same(2, cli(['--unknown'])[0]);
        same(2, cli(['--demo', '--two-player'])[0]);
        [$code, $out] = cli([]);
        same(0, $code);
        same(true, str_contains($out, 'ゲームを終了しました。'));
        [$code, $out, $err] = cli(['--two-player'], "help\na1\nz9\nd3\nq\n");
        same(0, $code);
        same('', $err);
        same(true, str_contains($out, 'そのマスには置けません'));
        same(true, str_contains($out, 'a1〜h8の座標'));
        same(true, str_contains($out, 'B > d3 (1 枚反転)'));
        same(true, str_contains($out, 'W の手 >'));
        [$code, $out] = cli([], "d3\nq\n");
        same(0, $code);
        same(true, str_contains($out, 'W >'));
    });

    test('CLI automatic match is deterministic and finishes', static function (): void {
        $first = cli(['--demo']);
        same(0, $first[0]);
        same('', $first[2]);
        same(true, str_contains($first[1], '対局終了:'));
        same($first, cli(['--demo']));
    });

    echo "\n$tests tests, $assertions assertions, $positions compared positions: PASS\n";
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: {$error->getMessage()}\n{$error->getTraceAsString()}\n");
    exit(1);
}
