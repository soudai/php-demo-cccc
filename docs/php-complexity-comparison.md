# PHPの複雑度が高いコードと低いコードを比較する

このドキュメントでは、短い条件分岐と実際のオセロの合法手判定を題材に、深い入れ子を役割ごとに分け、宣言的なコードへ整理する過程を説明します。「どの順にループや変数を操作するか」から「どの条件を満たす値が欲しいか」へ表現を変え、動作とCCCCの実測値を確かめます。導入・実行コマンドの詳細は[CCCCの使い方](cccc-guide.md)を参照してください。

以下の値は **CCCC 1.7.0で実測**しています。「高い」「低い」は比較対象間の違いを指し、PHPコード全般に対する絶対的な品質評価ではありません。

## 1. 小さな例：入れ子からガード節、宣言的な条件式へ

「ログイン済み」「有効なアカウント」「ブロックされていない」の3条件を満たすときだけ、ゲームに参加できるとします。

### 比較するPHPコード

```php
<?php

declare(strict_types=1);

function canPlayNested(bool $loggedIn, bool $active, bool $blocked): bool
{
    if ($loggedIn) {
        if ($active) {
            if (!$blocked) {
                return true;
            }
        }
    }
    return false;
}

function canPlayGuard(bool $loggedIn, bool $active, bool $blocked): bool
{
    if (!$loggedIn) {
        return false;
    }
    if (!$active) {
        return false;
    }
    if ($blocked) {
        return false;
    }
    return true;
}

function canPlayDeclarative(bool $loggedIn, bool $active, bool $blocked): bool
{
    return $loggedIn && $active && !$blocked;
}
```

上のコードを`/tmp/cccc-guard-clauses.php`として保存すると、リポジトリのルートから次のように再計測できます。

```bash
php -l /tmp/cccc-guard-clauses.php
.tools/cccc-linux/cccc --no-config --lang php --table /tmp/cccc-guard-clauses.php
```

### 実測結果

| 関数 | 認知的複雑度 | 循環的複雑度 |
| --- | ---: | ---: |
| `canPlayNested` | 6 | 4 |
| `canPlayGuard` | 3 | 4 |
| `canPlayDeclarative` | 1 | 3 |

入れ子の例では、最初の`if`が1、2段目が2、3段目が3となり、認知的複雑度は合計6です。ガード節では同じ深さの`if`が3つなので合計3になります。この例の早期`return`自体は値を増やしません。

一方、どちらにも判断が3つあるので、循環的複雑度は関数の基底値1と合わせて4です。ガード節は判断条件を削除していません。失敗条件を先に返すことで、成功する処理を読む間に覚えておく入れ子を減らしています。

宣言的な条件式では、「ログイン済み、かつ有効、かつブロックされていない」という参加条件をそのまま返します。途中で実行する処理がなく、真偽値が欲しいだけなので、`if`と`return`の組み合わせを1つの式に置き換えられます。この例では、CCCCは連続する`&&`を認知1、2つの`&&`と基底値を循環3として計測しています。条件の組み合わせ自体が減ったわけではありません。

3つの引数は真偽値なので入力は8通りです。3関数とも「`true`, `true`, `false`」の場合だけ`true`を返し、それ以外は`false`です。ドキュメント作成時に8通りの一致も確認しています。条件ごとに別のエラーを返す場合などは、ガード節を残す方が意図を表現しやすくなります。

この例から「早期returnにすれば常に良い」とは判断できません。リソースの解放や後処理を飛ばさないか、関数の意図が読みやすくなるかを併せて確認します。

## 2. オセロでは何を判定するのか

比較する機能は「現在のプレイヤーが石を置ける座標の一覧を返す」処理です。どの実装も、次の条件を満たす必要があります。

1. 置くマスが空いている。
2. 縦・横・斜めのいずれかの方向に、相手の石が1枚以上連続している。
3. その連続の先に自分の石があり、相手の石を挟める。
4. 盤面の外に出たり、途中で空きマスになったりした方向では挟めない。

同じ盤面とプレイヤーを入力し、同じ盤面順の座標一覧を返す条件で比較します。比較用実装は、`B`・`W`・`.`で構成された有効な8×8盤面と、`B`または`W`のプレイヤーを受け取る前提です。無効な入力の扱いまで同じという比較ではありません。

## 3. 複雑度が高い実装：1つのメソッドで探索する

[比較用のMoveFinder.php](../examples/baseline/MoveFinder.php)から、メソッド全体を抜粋します。クラス内のコードなので、この抜粋だけを単独のPHPファイルとして実行するものではありません。

```php
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
```

このメソッドの実測値は **認知的複雑度54、循環的複雑度17** です。

読み手は、盤面の位置、空きマスかどうか、探索方向、盤面境界、相手の石を見たか、最後が自分の石かを、一続きの入れ子の中で追います。合法手という1つの結果を得るまでに、複数の探索の状態が同じ場所に現れることが読み解きづらさにつながります。

行数だけが原因ではありません。空行やコメントを減らして短くしても、制御フローの入れ子は変わりません。また、このコードは比較専用で、ゲーム本体の実行には使っていません。

## 4. 複雑度を抑えた実装：処理の役割を分ける

ゲーム本体の[Board.php](../src/Board.php)は、盤面走査、反転する石の収集、1方向の判定を分けています。以下は同じクラスからの抜粋です。定数やその他のメソッドはリンク先のソースを参照してください。

### 盤面を走査して合法手を集める

```php
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
```

認知的複雑度は3、循環的複雑度は3です。64マスを1つのループで走査し、その場所で反転できる石があるかを問い合わせています。先頭の`opponent`呼び出しは、プレイヤーがB/Wのどちらかであることも検証します。

2重ループから1重ループへの変更だけを目的にすると、座標計算がかえって分かりにくい場合もあります。このデモでは盤面順を維持する単純な64マスの走査として採用しています。行数や点数だけでなく、座標の変換が読み手に伝わるかも評価します。

### 置けない条件を先に返し、8方向を調べる

```php
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
```

認知的複雑度は3、循環的複雑度は4です。盤面外と空きマスでない場合をガード節にして、その後は8方向の探索結果を集める処理に集中できます。境界を確認した後に盤面へアクセスする順序も保っています。

### 1方向に並ぶ石だけを判定する

```php
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
```

認知的複雑度は4、循環的複雑度は5です。相手の石が続く間だけ走査し、最後が盤面外または自分の石でなければ空の結果を返します。相手の石が1枚もなかった場合も、収集した`$line`が空なので反転できません。

分割によって、合法手を集めたい読み手は`legalMoves`、境界や挟み方を確認したい読み手は`scanDirection`に注目できます。こうした役割と名前の対応が、数値の改善とともに確認したい点です。

## 5. 宣言的な実装：欲しい結果を条件と変換で表す

[宣言的なMoveFinder.php](../examples/declarative/MoveFinder.php)では、同じ合法手判定を次の流れで表します。

| 欲しい結果 | コードでの表現 | 入れ子の実装から変わる点 |
| --- | --- | --- |
| 盤面順の全座標 | `positions()` | 盤面の走査を合法性の判定から分ける |
| 合法な座標だけ | `array_filter(..., isLegalAt)` | `$legal`の更新と`$moves[]`への追加を、選別条件として表す |
| 1方向でも挟めるか | `any(DIRECTIONS, canCaptureInDirection)` | 全方向の探索状態を持たず、条件を満たす方向の存在を問う |
| `d3`などの座標一覧 | `array_map(..., coordinate)` | 判定後の値を表示形式へ変換する |

### 入口で「列挙・選別・変換」を読めるようにする

以下は宣言的なクラスからの抜粋です。`SIZE`は8、`EMPTY`は`.`、`DIRECTIONS`は`[0, 0]`を除く8方向です。

```php
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
```

読み手は`$x`や`$y`の更新を追う前に、「合法な位置だけを座標文字列にしたい」という目的を確認できます。`array_filter`は元のキーを保ち、単一配列の`array_map`もそのキーを保つため、最後の`array_values`で`0, 1, 2, ...`の連番に戻します。これは比較元と同じ`list<string>`を返すための処理です。要素の盤面順は変えません。

初期盤面なら、黒では`['d3', 'c4', 'f5', 'e6']`、白では`['e3', 'f4', 'c5', 'd6']`になります。リポジトリのルートで、宣言的な実装だけを動かすこともできます。

```bash
php -r '
require "examples/declarative/MoveFinder.php";
$rows = [
    "........", "........", "........", "...WB...",
    "...BW...", "........", "........", "........",
];
print_r(\OthelloDemo\Declarative\MoveFinder::legalMoves($rows, "B"));
'
```

### 座標生成を単純な変換にする

以前の宣言的な実装では、各行を`array_map`で作り、`array_reduce`内で`[...$carry, ...$row]`として結合していました。これは「行ごとに作った配列を蓄積する」という手順を別途読む必要がありました。今の実装は0〜63を座標へ変換するだけです。

```php
/** @return list<array{int, int}> */
private static function positions(): array
{
    return array_map(
        static fn (int $index): array => [$index % self::SIZE, intdiv($index, self::SIZE)],
        range(0, self::SIZE * self::SIZE - 1)
    );
}
```

例えば0は`[0, 0]`、7は`[7, 0]`、8は`[0, 1]`です。行優先の順序を維持しながら、行ごとの配列結合をなくしています。`array_reduce`を使うこと自体が宣言的な設計の目的ではなく、入力と出力の関係が素直に読めることを優先します。

### 「空きマス、かつ挟める方向がある」を表す

```php
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

private static function any(array $items, callable $predicate): bool
{
    foreach ($items as $item) {
        if ($predicate($item)) {
            return true;
        }
    }
    return false;
}
```

`isLegalAt`を読むときは、空きマスの確認と「8方向のうち1つでも条件を満たすか」に集中できます。`any`は条件を満たした時点で`true`を返すため、残りの方向を調べません。`array_filter`で全方向の結果を作ってから件数を調べる必要はありません。このプロジェクトの最低要件であるPHP 8.2で動くよう、短絡評価する小さなヘルパーとして実装しています。

### 1方向の判定は真偽値を返す

以前の`capturesInDirection`は、反転できる石の座標を配列に集めて返していました。しかし呼び出し元が使っていた情報は`!== []`だけです。現在の`canCaptureInDirection`は、相手の石を1枚以上見たかと、その先が自分の石かを判定します。

```php
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
```

境界を先に確認するため、盤面外の文字にアクセスしません。相手の石がなく、隣がいきなり自分の石の場合も`false`です。連続した相手の石の先が空きマスや盤面外なら、やはり`false`になります。使わない反転座標を保存せず、合法手の判定に必要な情報だけを保持します。実際に石を反転する本体の`Board::scanDirection`では座標一覧が必要なので、役割に応じて戻り値を選びます。

宣言的なコードでも、低い層では`foreach`や`while`を使います。盤面を変更せず、入力から判定結果を返す関数へ探索を閉じ込めることで、呼び出し元をルールに沿った言葉で読めるようにしています。すべてのループを高階関数に置き換える必要はありません。

## 6. 呼び出し先も含めて比較する

入口の`legalMoves`だけを見ると、入れ子の実装は認知54・循環17、本体の`Board`は認知3・循環3、宣言的な実装は認知0・循環1です。ただし、整理後は処理をヘルパーやコールバックへ移しているため、それだけで機能全体の複雑度が同じ比率で減ったとは言えません。認知0も「理解する負担がない」という意味ではありません。

まず本体の`Board`で、合法手判定が利用する6メソッドをそれぞれ1回ずつ集計します。

| メソッド | 役割 | 認知 | 循環 |
| --- | --- | ---: | ---: |
| `legalMoves` | 盤面走査と座標一覧の作成 | 3 | 3 |
| `flipsAt` | 入力位置の確認と8方向の集約 | 3 | 4 |
| `scanDirection` | 1方向の石の連続と終端の判定 | 4 | 5 |
| `inside` | 盤面境界の確認 | 1 | 4 |
| `opponent` | 相手の色の取得・プレイヤーの検証 | 1 | 3 |
| `coordinate` | 座標の検証と文字列化 | 1 | 2 |
| **合計** | | **13** | **21** |

これはこのデモで定義した集計範囲です。実行時の呼び出し回数を掛けた値でも、CCCCが自動計算した呼び出しグラフ全体の複雑度でもありません。PHP標準関数の内部や、盤面を作るコンストラクターは含みません。コンストラクターなどの値は本体全体のレポートで別に確認できます。

宣言的な実装は、ファイル内の8メソッドと4つの矢印関数をすべて含めます。CCCCはコールバックも独立した関数として計測するため、`legalMoves`の値だけでは選別や変換の全体を評価できません。

| メソッド・コールバック | 個数 | 認知の合計 | 循環の合計 |
| --- | ---: | ---: | ---: |
| `legalMoves` | 1 | 0 | 1 |
| `legalMoves`内の選別・変換の矢印関数 | 2 | 0 | 2 |
| `positions`と座標生成の矢印関数 | 2 | 0 | 2 |
| `isLegalAt`と方向判定の矢印関数 | 2 | 1 | 3 |
| `any` | 1 | 3 | 3 |
| `canCaptureInDirection` | 1 | 3 | 5 |
| `inside` | 1 | 1 | 4 |
| `opponent` | 1 | 1 | 2 |
| `coordinate` | 1 | 0 | 1 |
| **合計** | **12** | **9** | **23** |

3つの実装を同じ合法手判定の範囲で並べると、次のようになります。

| 比較範囲 | 関数数 | 認知の最大値 | 認知の合計 | 循環の最大値 | 循環の合計 |
| --- | ---: | ---: | ---: | ---: | ---: |
| 入れ子の比較用実装 | 1 | 54 | 54 | 17 | 17 |
| 本体の`Board`・ヘルパーを含む | 6 | 4 | 13 | 5 | 21 |
| 宣言的な実装・ヘルパーとコールバックを含む | 12 | 3 | 9 | 5 | 23 |

この結果から、読む単位ごとの最大値が小さくなり、入れ子の負担が分散・削減されていることを確認できます。一方で、循環的複雑度の合計は比較元の17に対し、本体は21、宣言的な実装は23です。`Board`は反転座標の収集や入力検証も担い、宣言的な実装は有効な入力に対する合法手の有無を判定するため、この差には責務の違いも含まれます。

既存の宣言的な実装に対する今回の改善では、座標生成のコールバックが1つ減り、方向判定が認知5・循環6から認知3・循環5へ変わりました。ファイル全体の認知合計は11→9、循環合計は25→23です。配列結合と不要な座標の保存は省きましたが、実行速度の改善を測定したものではありません。

### 循環的複雑度の合計が増える理由

循環的複雑度は各関数に基底値1を持ちます。1つの関数を6つに分ければ、集計に含まれる基底値も1つから6つになります。さらに、この比較では共通ヘルパーの入力検証や座標変換の分岐も数えています。

宣言的な実装の12関数には、4つの矢印関数の基底値も含まれます。また、標準関数の`array_filter`や`array_map`の内部ループはこのPHPソースの計測には含まれません。ループの記述が呼び出し元からなくなっても、実行時の走査がなくなるわけではありません。

そのため、合計だけを取り出して「分割後のコードの方が悪い」とも、「機能全体の分岐が大幅に減った」とも判断できません。単純な関数分割、入れ子の削減、検証処理の違いを区別して評価します。数値を下げるために入力検証やゲームのルールを削るのは目的に合いません。

同様に、循環的複雑度21だからテストが21件あれば十分、という読み方もできません。盤面の境界、状態の組み合わせ、複数手にわたるゲーム進行は別に考える必要があります。

## 7. 同じ動作を維持できたか確認する

[自動テスト](../tests/run.php)では、20個の固定seedで初期盤面から対局を進め、各盤面で黒・白の両方について、入れ子の比較用実装・本体の`Board`・宣言的な実装の合法手一覧を比較します。厳密な配列比較により座標だけでなく順序とキーも一致することを確認します。対局が最後まで進むことに加え、着手後に空きマスが1つ減ること、石の増減と反転数が一致することも検証します。

さらに、次のルールを個別に確認しています。

- 初期盤面の合法手と得点。
- 8方向同時の反転、端での反転、挟めない石の列、盤面の回り込み防止。
- 不正な座標や置けないマスでは盤面が変わらないこと。
- 強制パス、空きマスを残した終局、満杯の盤面と引き分け。
- CLIの入力、EOF、CPU対戦、自動対局の再現性。

宣言的な実装には、黒・白それぞれの8方向の挟み込み、相手の石がない場合、途中に空きマスがある場合、端で途切れる列、盤面の回り込み、占有済みのマスを直接確認するテストも追加しています。複数方向で挟めても座標が重複しないこと、返す一覧が盤面順の連番キーになることも検証します。

```bash
php tests/run.php
```

保存済みの結果は **13テスト、12,214アサーション、1,220比較盤面で成功**です。実行ログは[tests.txt](../reports/tests.txt)にあります。1,220は固定seedでの対局中に比較した盤面数で、その各盤面に対して両プレイヤーを比較しています。個別の境界テストの盤面数は含みません。全ての到達可能な盤面について等価性を証明したものではありません。

## 8. 自分で計測と評価を再現する

リポジトリのルートで、CCCCの導入後に実行します。

```bash
# まず3実装の数値を、閾値の影響なしで表示
.tools/cccc-linux/cccc --no-config --lang php --table \
  examples/baseline/MoveFinder.php src/Board.php examples/declarative/MoveFinder.php

# 比較用実装には品質ゲートを適用すると違反が見つかる
.tools/cccc-linux/cccc --config cccc.toml --table examples/baseline
echo $?  # 1: 認知54・循環17が閾値を超える

# 本体全体は品質ゲートを通過する
.tools/cccc-linux/cccc --config cccc.toml --table src bin bootstrap.php
echo $?  # 0: 各関数の認知は15以下、循環は10以下

# 宣言的な比較用実装も、同じ閾値で個別に確認
.tools/cccc-linux/cccc --config cccc.toml --table examples/declarative
echo $?  # 0

# 3実装の動作確認と、本体・baselineのレポート生成をまとめて実行
bash scripts/demo.sh
```

`scripts/analyze.php`が生成するHTML・JSON・Markdownの比較対象は、本体と`baseline`です。宣言的な実装の数値は、上の直接計測コマンドで確認します。`Board.php`全体のファイル合計には着手や得点計算なども含まれるため、第6節の6メソッドの合計とは区別してください。

失敗時に停止する`set -e`を有効にしたシェルでは、比較用コードの想定内の終了コード1でもそこで止まります。上の例は対話シェルで1つずつ実行する手順です。デモ全体のスクリプトでは、本体の失敗と比較用の想定内の違反を区別しています。

改善案を試すときは、変更前の値と対象を控え、1つの役割ずつ整理して、テストと同じ範囲の計測を繰り返します。この例で評価したいのは「最大値が下がったか」に加えて、「名前から責務が分かるか」「呼び出し先を含めて読みやすいか」「ルールを維持できたか」です。

## 参照

- [CCCCの使い方](cccc-guide.md)：オプション、設定、終了コード、解析エラーの扱い。
- [本体の実測JSON](../reports/application.json)・[比較用の実測JSON](../reports/baseline.json)：オセロの数値の根拠。
- [計測条件とソースのSHA256](../reports/metadata.json)：バージョンと対象の確認。
- [評価レポート](../reports/summary.md)：本体全体の関数別一覧。
- [宣言的・関数型スタイル改善レポート](othello-declarative-functional-report.md)：宣言的な実装の変更点と実測値。
- [CCCC 1.7.0](https://github.com/moznion/cccc/tree/v1.7.0)：使用したツールの公式ソース。
