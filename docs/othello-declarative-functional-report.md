# 宣言的・関数型スタイルによるオセロ合法手判定の複雑度改善レポート

## 目的

既存の比較用スクリプト（入れ子が深い実装）と同じオセロ仕様を維持したまま、宣言的・関数型スタイルで複雑度を下げることを目的に改善を実施しました。

- 既存（比較元）: `examples/baseline/MoveFinder.php`
- 改善後（比較先）: `examples/declarative/MoveFinder.php`

どちらも仕様は「有効な8x8盤面 (`B` / `W` / `.`) と手番 (`B` or `W`) を受け取り、行優先順・連番キーで合法手一覧を返す」です。無効な入力への対応は比較の対象外です。

改善前後のコードを段階的に読むには、[PHPの複雑度比較](php-complexity-comparison.md)を参照してください。

## 実装方針

改善後は、1メソッドに集中していた責務を以下に分割しました。

- `legalMoves`: 盤面座標の列挙・フィルタ・座標文字列化
- `isLegalAt`: 1マスの合法性判定
- `canCaptureInDirection`: 1方向で相手の石を挟めるかの判定
- `inside` / `opponent` / `coordinate`: 純粋関数として共通化
- `positions`: 盤面全座標の生成
- `any`: 条件を満たす方向があるかの抽象化

これにより、

- 深い多重ループ＋多重条件分岐を縮小
- ルール判定を関数合成的に記述
- 各関数の認知負荷を局所化

を実現しています。

## 宣言的な実装をさらに整理した点

`positions`は、各行を生成して`array_reduce`と配列展開で結合する実装から、0〜63を`[x, y]`へ変換する1回の`array_map`へ変更しました。行ごとの蓄積を追わずに、行優先の座標生成を読めます。

方向別の探索は、反転座標を配列で返す`capturesInDirection`から、真偽値を返す`canCaptureInDirection`へ変更しました。呼び出し元が必要としていたのは「反転できるか」だけなので、相手の石を見つけたかを記録し、その先が自分の石なら`true`を返します。境界確認は盤面へのアクセスより先に行います。

`any`は成功した方向が見つかれば探索を終了します。局所的な`while`や`foreach`は残し、呼び出し元を「列挙・選別・変換」として表す構造を保っています。今回省いた配列生成が実行速度に与える影響は測定していません。

## CCCC計測コマンド

```bash
.tools/cccc-linux/cccc --no-config --lang php --table \
  examples/baseline/MoveFinder.php \
  examples/declarative/MoveFinder.php
```

## 計測結果（CCCC 1.7.0で実測）

### 比較元（baseline）

- `legalMoves`: Cognitive **54**, Cyclomatic **17**
- ファイル合計: Cognitive **54**, Cyclomatic **17**

### 比較先（declarative）

- 最大値
  - Cognitive 最大: **3**（`any`、`canCaptureInDirection`）
  - Cyclomatic 最大: **5**（`canCaptureInDirection`）
- ファイル合計（8メソッドと4つの矢印関数）
  - Cognitive 合計: **9**
  - Cyclomatic 合計: **23**

| 範囲 | 関数数（コールバックを含む） | Cognitive 最大 | Cognitive 合計 | Cyclomatic 最大 | Cyclomatic 合計 |
| --- | ---: | ---: | ---: | ---: | ---: |
| baseline | 1 | 54 | 54 | 17 | 17 |
| 今回の整理前のdeclarative | 13 | 5 | 11 | 6 | 25 |
| 現在のdeclarative | 12 | 3 | 9 | 5 | 23 |

`scripts/analyze.php`が保存する本体・baselineのレポートには、宣言的な実装は含まれていません。この表の現在値は上の直接計測コマンドで再確認できます。

## どう改善されたか

### 1) 複雑な1関数の分解で「最大値」が大幅改善

- Cognitive: **54 → 3（最大値）**
- Cyclomatic: **17 → 5（最大値）**

1関数に集中していた入れ子を分解し、読む単位ごとの分岐や入れ子を減らしました。入口の`legalMoves`だけでなく、呼び出し先とコールバックも含めた最大値です。

### 2) Cognitive合計も削減

- Cognitive合計: **54 → 9**

入れ子の解消と責務分離の効果が数値にも表れています。ただし、数値は読みやすさの目安です。ヘルパーの名前や、処理を追う際の関数間の移動も含めて評価します。

### 3) Cyclomatic合計は増加（分割の副作用）

- Cyclomatic合計: **17 → 23**

関数分割で基底値（各関数に最低1）が増えることが影響しています。`array_filter` / `array_map` / `any`へ渡す矢印関数も個別関数として計上されます。標準関数の内部ループは、このPHPソースの集計には含まれません。実行時に必要な走査までなくなったわけではありません。

したがって、**関数単位の最大値、集計範囲、責務、テスト結果**を併せて評価します。

## 仕様同等性の確認

`tests/run.php` に宣言的実装との同値比較を追加し、既存テストを通過済みです。

実行結果:

- **13 tests, 12214 assertions, 1220 compared positions: PASS**
- 実行ログ: [reports/tests.txt](../reports/tests.txt)

比較ポイント:

- 20 seed の完走対局で各盤面・各手番について
  - baseline実装
  - 本体実装（`src/Board.php`）
  - 宣言的実装

の合法手が一致することを検証。

さらに、両プレイヤーの8方向の挟み込み、占有済みマス、相手の石がない場合、途切れた列、盤面端と回り込み、長い列、複数方向での挟み込み、連番キーと盤面順を個別に確認しています。1,220は対局中の比較盤面数で、個別の境界テストは含みません。全到達可能盤面の等価性を証明したものではありません。

## 変更ファイル

- `examples/declarative/MoveFinder.php`: 宣言的な比較用実装と今回の整理。
- `tests/run.php`: 3実装の一致と宣言的な判定の境界条件。
- `docs/php-complexity-comparison.md`: コード例、改善の理由、呼び出し先を含む比較。
- `reports/tests.txt`: 最新のテスト実行結果。
