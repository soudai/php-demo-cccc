# PHP Othello / CCCC 実測レポート

計測日時: 2026-10-03T14:34:24+09:00 / cccc 1.7.0 / PHP 8.3.6 / Linux

対象: `src/`, `bin/`, `bootstrap.php`。テスト、レポート生成コード、比較用コードは本体集計の対象外。

## 本体の評価

- 品質ゲート: **PASS**（関数ごとに認知的複雑度 ≤ 15、循環的複雑度 ≤ 10）
- 6 ファイル / 27 関数 / 解析エラー 0
- 認知的複雑度: 最大 7 / 合計 65
- 循環的複雑度: 最大 6 / 合計 81

閾値はこのデモの目安です。低い値だけで正しさ、保守性、性能を保証するものではありません。

## 同じ合法手判定の比較

| 範囲 | 関数数 | 認知 最大 | 認知 合計 | 循環 最大 | 循環 合計 |
| --- | ---: | ---: | ---: | ---: | ---: |
| 入れ子の多い実装 | 1 | 54 | 54 | 17 | 17 |
| 整理後（呼び出すヘルパー含む） | 6 | 4 | 13 | 5 | 21 |

入口の `legalMoves` 単体は認知 54 → 3、循環 17 → 3。分割先を隠さないよう、上表では `legalMoves / flipsAt / scanDirection / inside / opponent / coordinate` を各1回集計しています。検証済みの盤面とB/Wを入力する条件で比較しています。

整理後は盤面走査、8方向の集約、1方向の判定に役割を分け、ガード節で入れ子を減らしています。循環的複雑度の合計には各関数の基底値と共通ヘルパーの分岐も含まれるため、関数分割で合計が増えることがあります。合計だけで優劣を決めず、関数単位の値・責務・テスト結果を併せて評価してください。

比較用コードのゲート: **FAIL（比較用の想定結果）**（exit 1）。本体のゲートとは分けて実行します。

## 本体の関数一覧（認知的複雑度の降順）

| 関数 | ファイル:行 | 認知 | 循環 |
| --- | --- | ---: | ---: |
| `playGame` | src/Console.php:31 | 7 | 6 |
| `readMove` | src/Console.php:68 | 7 | 5 |
| `__construct` | src/Board.php:21 | 6 | 6 |
| `choose` | src/Computer.php:12 | 6 | 5 |
| `render` | src/Console.php:88 | 6 | 4 |
| `run` | src/Console.php:11 | 5 | 6 |
| `scanDirection` | src/Board.php:108 | 4 | 5 |
| `flipsAt` | src/Board.php:89 | 3 | 4 |
| `<closure>` | bootstrap.php:5 | 3 | 3 |
| `legalMoves` | src/Board.php:74 | 3 | 3 |
| `play` | src/Board.php:127 | 2 | 3 |
| `applyMove` | src/Console.php:54 | 2 | 3 |
| `skipBlockedPlayer` | src/Game.php:54 | 2 | 3 |
| `result` | src/Game.php:65 | 2 | 3 |
| `play` | src/Game.php:42 | 2 | 2 |
| `inside` | src/Board.php:44 | 1 | 4 |
| `opponent` | src/Board.php:35 | 1 | 3 |
| `parseCoordinate` | src/Board.php:50 | 1 | 2 |
| `coordinate` | src/Board.php:59 | 1 | 2 |
| `isOver` | src/Game.php:36 | 1 | 2 |
| `rows` | src/Board.php:68 | 0 | 1 |
| `score` | src/Board.php:142 | 0 | 1 |
| `help` | src/Console.php:105 | 0 | 1 |
| `__construct` | src/Game.php:13 | 0 | 1 |
| `board` | src/Game.php:21 | 0 | 1 |
| `currentPlayer` | src/Game.php:26 | 0 | 1 |
| `passedPlayer` | src/Game.php:31 | 0 | 1 |

認知的複雑度は分岐や入れ子による読み解きづらさ、循環的複雑度は制御フローの分岐を捉える指標です。仕様: [CCCC](https://github.com/moznion/cccc/tree/v1.7.0)。生データは `application.json` と `baseline.json`、実行条件・ソースのSHA256は `metadata.json`、テスト結果は `tests.txt`、自動対局は `game.txt` を参照してください。
