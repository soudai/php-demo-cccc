# CCCCでPHPの複雑度を計測する

このガイドは、CLIオセロのソースを題材に、CCCCの導入、計測、結果の読み方、品質ゲートの使い方を説明します。コードの書き方による違いは、[PHPの複雑度比較](php-complexity-comparison.md)で扱います。

説明と数値は **CCCC 1.7.0** に基づきます。実行例はWSL / UbuntuのBash用で、リポジトリのルートを作業ディレクトリとします。

## 1. 何を計測するのか

CCCCはソースコードを解析し、関数・メソッドなどの複雑度を数値にするツールです。PHPコードを実行して速度を測るツールではありません。

| 指標 | このデモでの読み方 | 注目する箇所 |
| --- | --- | --- |
| 認知的複雑度（Cognitive Complexity） | 制御フローを読み解く負担の目安 | 深い入れ子、複数の処理が混在する関数 |
| 循環的複雑度（Cyclomatic Complexity） | 制御フロー上の独立した経路の数を捉える指標 | 分岐やループ、条件式の組み合わせ |

2つの指標は同じものを数えていません。例えば、3段の`if`を3つのガード節に変えると、この後の比較例では認知的複雑度が下がり、循環的複雑度は変わりません。

値はレビューの入口として使います。複雑度が低くても、ルールを間違えたオセロや、意図の伝わらないコードは作れてしまいます。動作の正しさはテスト、速度は性能測定で別に確認します。

## 2. WSLで準備する

初回は[READMEの導入手順](../README.md#wslで起動)に従ってリポジトリを用意します。PHP 8.2以上、Bash、curl、tar、sha256sumが必要です。

```bash
bash scripts/setup.sh

# 以降の例で使う、プロジェクト内の実行ファイル
CCCC='./.tools/cccc-linux/cccc'
"$CCCC" --version
php --version
```

CCCCの出力は`cccc 1.7.0`です。セットアップは公式のLinux向けリリースを取得し、固定したSHA256を照合してから展開します。システムのPATHは変更しません。別のターミナルを開いた場合は、`CCCC`変数を再設定してください。

## 3. まず本体を表で見る

```bash
"$CCCC" --config cccc.toml --table src bin bootstrap.php
```

この指定では、盤面やゲーム進行を実装する`src/`、CLI入口の`bin/`、オートローダーの`bootstrap.php`だけを計測します。

本デモの保存済み実測結果は次のとおりです。

| 項目 | 認知的複雑度 | 循環的複雑度 |
| --- | ---: | ---: |
| 関数ごとの最大値 | 7 | 6 |
| 全関数の合計 | 65 | 81 |

対象は6ファイル・27関数で、解析エラーは0件です。出典は[本体のJSON](../reports/application.json)と[計測条件](../reports/metadata.json)。ソースを変更した場合は再計測した結果を使ってください。

ここでの「最大値」は1つの関数の値です。「合計」は複数の関数を集計した値であり、1つの大きな関数を解析したときの値ではありません。

### 1ファイルだけを調べる

```bash
"$CCCC" --config cccc.toml --table src/Board.php
```

### 高い関数を先に読む

```bash
# 認知的複雑度の上位10件
"$CCCC" --config cccc.toml --table --top-cognitive 10 src bin bootstrap.php

# 循環的複雑度の上位10件
"$CCCC" --config cccc.toml --table --top-cyclomatic 10 src bin bootstrap.php

# いずれかの指標が5以上の関数を表示
"$CCCC" --config cccc.toml --table --min 5 src bin bootstrap.php
```

`--top-cognitive`と`--top-cyclomatic`は同時に指定できません。`--min`や`--top-*`で表示を絞っても、サマリーと閾値判定は絞り込み前の対象に基づきます。上位10件の表に出なかった関数が、解析対象から外れたわけではありません。

## 4. JSONを読む

```bash
"$CCCC" --config cccc.toml --pretty src bin bootstrap.php
```

CCCCの標準出力は既定でJSONです。`--pretty`で整形し、`--table`で表に切り替えます。本リポジトリの設定には`pretty = true`があるため、このオプションを省略しても整形されます。

[application.json](../reports/application.json)には、例えば次の関数情報が含まれます。

```json
{
  "name": "legalMoves",
  "kind": "method",
  "line": 74,
  "cognitive": 3,
  "cyclomatic": 3
}
```

| フィールド | 確認すること |
| --- | --- |
| `files[].path` | 意図したファイルを解析しているか |
| `files[].functions[]` | 関数名・種類・位置と、各関数の値 |
| `files[].cognitive` / `cyclomatic` | ファイル内の集計値。関数単位の閾値と混同しない |
| `summary.file_count` / `function_count` | 対象が空になったり、想定より減ったりしていないか |
| `summary.parse_error_count` | 解析エラーがないか |
| `summary.cognitive` / `cyclomatic` | 合計・最大・中央値・パーセンタイルで見た全体の分布 |

関数名はファイルをまたいで重複するため、`path`や`line`と一緒に読みます。`--top-*`のJSONは`files`配列ではなく`metric`・`top`・`summary`を持つランキング形式になるので、スクリプトで読む際は区別します。

## 5. 閾値を決めて品質ゲートにする

このリポジトリの[cccc.toml](../cccc.toml)は次の設定です。

```toml
languages = ["php"]
max-cognitive = 15
max-cyclomatic = 10
pretty = true
```

意味は「各関数の認知的複雑度が15以下、かつ循環的複雑度が10以下であること」です。**いずれか一方でも上限を超えた関数があれば失敗**します。上限と同じ値は許容されます。

この15と10はデモ用の基準であり、全PHPプロジェクトに共通する合否基準ではありません。既存コードの分布や変更頻度、チームのレビュー方針に合わせて決めます。本体の認知的複雑度の合計65を15と比較して失敗扱いにする設定でもありません。

### 閾値に違反する例を確認する

```bash
"$CCCC" --config cccc.toml --table examples/baseline
echo $?
```

比較用の`legalMoves`は認知54・循環17なので、終了コードは`1`です。これはデモで意図した閾値違反であり、オセロの自動テストが失敗したという意味ではありません。

### 設定の影響を外して比較する

```bash
"$CCCC" --no-config --lang php --table src/Board.php examples/baseline
```

この実行には閾値を指定していないため、複雑な関数があっても、その値だけを理由に終了コード1にはなりません。`--no-config`はカレントディレクトリや親ディレクトリの設定を読み込まない指定です。

設定の優先順位はコマンドライン、設定ファイル、既定値の順です。例えば、設定ファイルを使いつつ今回だけ認知的複雑度の上限を8にできます。

```bash
"$CCCC" --config cccc.toml --max-cognitive 8 --table src bin bootstrap.php
```

この場合も、設定ファイルの循環的複雑度の上限10は適用されます。

### 終了コードだけでは解析の成立を判断しない

CCCC 1.7.0の終了コードは次の扱いです。

| コード | 意味 |
| ---: | --- |
| 0 | 閾値違反などによる失敗なし。既存の入力パスに対象ファイルがない場合も含む |
| 1 | 関数の複雑度が指定した閾値を超過 |
| 2 | 存在しない入力パス、不正な設定や引数など、処理を進められないエラー |

解析エラーがあっても、終了コードだけで検出できるとは限りません。部分的な解析結果を完全な計測と誤認しないよう、`summary.parse_error_count`と対象件数を確認します。これは[CCCC 1.7.0のCLI実装](https://github.com/moznion/cccc/blob/v1.7.0/crates/cccc-cli/src/lib.rs)に基づく注意点です。

このデモの[analyze.php](../scripts/analyze.php)は、JSONの解析エラーが0件であることと、関数数が0でないことも確認します。これらを満たさなければレポート生成処理を失敗させます。

## 6. 計測対象を揃える

比較の前後で対象が変わると、実装改善ではなく集計範囲の違いを見ている可能性があります。このデモでは次のように分けています。

| 対象 | パス | 目的 |
| --- | --- | --- |
| アプリ本体 | `src/`, `bin/`, `bootstrap.php` | 本体の品質ゲートと関数一覧 |
| 比較専用コード | `examples/baseline/` | 入れ子の多い実装との対比 |
| テスト・計測処理 | `tests/`, `scripts/` | 上記の本体集計には含めない |

ディレクトリ探索では`.gitignore`が考慮されます。例えば別のPHPプロジェクト全体を調べるときは、解析対象外にしたい依存ライブラリやテストを明示できます。

```bash
# 本リポジトリで範囲を明示するなら、通常は src bin bootstrap.php の指定で十分
"$CCCC" --config cccc.toml --table \
  --exclude 'vendor/**' --exclude '.tools/**' \
  --exclude 'tests/**' --exclude 'scripts/**' --exclude 'examples/**' .
```

シェルに展開させないようglobは引用符で囲みます。対象が見つからないときは、パス、拡張子、`--lang`、除外設定、`.gitignore`を確認します。`--no-ignore`は除外の影響を調べる場合に使い、比較対象を不用意に増やさないようにします。

## 7. このデモのレポートを作る

```bash
# テスト・自動対局・CCCC計測をまとめて実行
bash scripts/demo.sh

# 計測とレポート生成だけを実行
php scripts/analyze.php
```

本体のゲート違反はデモ全体を失敗させます。比較用コードのゲート違反は想定内として別に記録します。生のCCCCコマンドは表・JSONを出し、HTMLとMarkdownは本デモのPHPスクリプトが生成しています。

| ファイル | 用途 |
| --- | --- |
| [complexity.html](../reports/complexity.html) | ローカルのブラウザで見る比較チャートと関数一覧 |
| [summary.md](../reports/summary.md) | 実測値と評価を文章で読む |
| [application.json](../reports/application.json) / [baseline.json](../reports/baseline.json) | CCCCの出力を確認する |
| [metadata.json](../reports/metadata.json) | バージョン、引数、終了コード、閾値、入力ソースのSHA256を確認する |
| [tests.txt](../reports/tests.txt) / [game.txt](../reports/game.txt) | テスト結果と自動対局を確認する |

`analyze.php`だけではテストと棋譜は更新されません。結果を一式揃えるときは`demo.sh`を使います。生成済みレポートはスナップショットであり、ソースの編集後も自動更新されるわけではありません。

## 8. レビューでの使い方

1. 対象件数と解析エラーを確認し、計測が成立しているか判断する。
2. 上位の関数を読み、入れ子や責務の混在など、理解しづらい理由を探す。
3. 小さな単位で整理し、同じ計測範囲とバージョンで数値を比較する。
4. 抽出先のヘルパーも確認し、名前と責務が明確になったかレビューする。
5. テストで動作の維持を確認し、数値とコードの両方から採用を判断する。

具体例は[PHPの複雑度比較](php-complexity-comparison.md)を参照してください。オプションの一次資料は[CCCC 1.7.0のREADME](https://github.com/moznion/cccc/blob/v1.7.0/README.md)です。
