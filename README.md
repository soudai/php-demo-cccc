# PHP Othello × CCCC

PHPで遊べるCLIオセロを題材に、[CCCC](https://github.com/moznion/cccc) で認知的複雑度（Cognitive Complexity）と循環的複雑度（Cyclomatic Complexity）を実測するデモです。WSL / Ubuntu向け。ComposerやWebサーバーは不要です。

## 解説ドキュメント

- [CCCCの使い方](docs/cccc-guide.md)：計測コマンド、JSONの読み方、設定と品質ゲート、解析時の注意点。
- [PHPの複雑度比較](docs/php-complexity-comparison.md)：入れ子とガード節、オセロの実装比較、ヘルパーを含む実測値の評価。
- [宣言的・関数型スタイル改善レポート](docs/othello-declarative-functional-report.md)：同一仕様での複雑度改善とCCCC実測値の比較。

## WSLで起動

必要環境: PHP **8.2以上**、Bash、curl、tar、sha256sum。PHPがない場合だけ、Ubuntuでインストールします。

```bash
sudo apt-get update
sudo apt-get install -y php-cli curl
```

WSLのUbuntuターミナルでリポジトリをcloneし、実行します。

```bash
git clone https://github.com/soudai/php-demo-cccc.git
cd php-demo-cccc
bash scripts/setup.sh
php bin/othello.php
```

`setup.sh` は公式の **CCCC v1.7.0 Linux版**を `.tools/cccc-linux/` に配置し、固定したSHA256で検証します。x86_64 / aarch64対応。システムのPATHは変更しません。導入後の対戦・テスト・計測はネット接続不要です。

Windows PowerShellからWSLを使う場合（既定ディストリビューション名は `Ubuntu-24.04`）:

```powershell
.\scripts\wsl.ps1 setup
.\scripts\wsl.ps1 play
.\scripts\wsl.ps1 demo
```

別名のWSLディストリビューションでは、そのUbuntuターミナルからBashの手順を実行してください。

## 遊び方

```bash
php bin/othello.php                 # あなた（黒）vs CPU（白）
php bin/othello.php --two-player    # 同じ端末で2人対戦
php bin/othello.php --demo          # CPU同士の自動対局
php bin/othello.php --help
```

- `d3` のように列 `a〜h` と行 `1〜8` を入力します。大文字や前後の空白も使えます。
- `B` が黒、`W` が白、`*` は現在の手番で置けるマスです。
- 置けない場合は再入力。合法手がない場合は自動パスします。
- 双方に合法手がなくなると終了し、石の数で勝敗を判定します。
- `help` で説明、`q` / `quit` / `exit` またはEOFで終了します。
- CPUは角を優先し、次に反転数を選ぶ簡単な戦略です。同点は盤面順で決めるため、デモの棋譜を再現できます。

## デモを一括実行

```bash
bash scripts/demo.sh
```

以下を順番に実行し、失敗した段階で停止します。

1. PHP構文チェックと自動テスト（ルール・CLI・比較実装の一致）
2. CPU同士の終局までの自動対局
3. CCCCによる本体と比較用コードの計測、品質ゲート判定、レポート生成

`reports/complexity.html` をWindowsのブラウザで開くと比較チャートと関数別の結果を閲覧できます。HTMLは外部ライブラリやCDNに依存しません。

| 出力 | 内容 |
| --- | --- |
| `reports/complexity.html` | 比較チャート・本体の関数別スコア |
| `reports/summary.md` | 数値と評価の文章 |
| `reports/application.json` | CCCCの本体解析結果をそのまま保存 |
| `reports/baseline.json` | 比較用コードの解析結果をそのまま保存 |
| `reports/metadata.json` | バージョン・実行引数・終了コード・閾値・入力ソースSHA256 |
| `reports/*-gate.txt` | CCCCの標準エラー出力（ゲートの診断等） |
| `reports/tests.txt` | テスト実行結果 |
| `reports/game.txt` | 全手順の盤面と自動対局の結果 |

レポートは計測時点のスナップショットです。コードを編集したら再実行してください。`php scripts/analyze.php` は計測だけを更新するため、テストと棋譜も更新したい場合は一括デモを実行します。

## CCCCを直接使う

```bash
# 本体だけを計測。cccc.tomlの閾値を適用
.tools/cccc-linux/cccc --table src bin bootstrap.php

# 認知的複雑度が高い関数から確認
.tools/cccc-linux/cccc --table --top-cognitive 10 src bin bootstrap.php

# 比較用実装。閾値を超えるので終了コード1になることを確認
.tools/cccc-linux/cccc --table examples/baseline
echo $?

# 閾値を適用せず、全比較対象の生データを確認
.tools/cccc-linux/cccc --no-config --lang php --pretty src examples/baseline

# HTML・JSON・評価文を再生成
php scripts/analyze.php
```

別のCCCCを使う場合は `CCCC_BIN=/absolute/path/to/cccc php scripts/analyze.php` と指定できます。バージョンが異なると結果が変わる可能性があります。

`cccc.toml` の本デモの基準は、各関数で **認知的複雑度 ≤ 15 / 循環的複雑度 ≤ 10** です。値が上限を超えるとゲートが失敗します。比較用コードのゲート失敗は想定内として記録し、本体のゲート失敗はデモ全体を失敗させます。解析エラーや空の計測結果は成功と扱いません。

## 比較の読み方

`examples/baseline/MoveFinder.php` は、盤面・方向・連続する石の探索を一つのメソッドに入れた比較専用コードです。本体は `src/Board.php` で、盤面走査、方向ごとの探索、境界判定を分けています。

入口の `legalMoves` だけで比較すると、別関数へ移した複雑度が見えなくなります。そのため、レポートには整理後の6メソッド（`legalMoves`, `flipsAt`, `scanDirection`, `inside`, `opponent`, `coordinate`）の最大値・合計も表示します。入力は有効な8×8盤面とB/Wに限定して比較しています。Boardの構築時検証などは本体全体の表に含めています。

認知的複雑度は入れ子の整理の効果を確認する指標です。循環的複雑度は各関数の基底値を含むため、関数分割で合計が増える場合があります。どちらも数値だけで正しさや性能を保証できません。20個の固定seedによる完走対局の各盤面で両実装の合法手の一致を確認し、別途ルールの境界条件もテストします。これは全盤面の等価性証明ではありません。

## ファイル構成

```text
bin/othello.php                 CLI入口
bootstrap.php                  小さなオートローダー
src/Board.php                  盤面、座標、合法手、反転、得点
src/Game.php                   手番、パス、終局、勝敗
src/Computer.php               決定的なCPU戦略
src/Console.php                入出力と盤面表示
examples/baseline/MoveFinder.php 比較用の入れ子の多い実装
examples/declarative/MoveFinder.php 宣言的・関数型スタイルの比較用実装
tests/run.php                  外部ライブラリ不要の自動テスト
scripts/setup.sh               WSL用CCCC導入
scripts/demo.sh                テスト・対局・計測の一括実行
scripts/wsl.ps1                PowerShellからWSLを呼ぶラッパー
scripts/analyze.php            CCCC呼び出しと結果保存
scripts/report*.php            HTML・Markdownレポート生成
cccc.toml                     計測の言語と品質ゲート
reports/                      実行結果
```

CCCCの計測対象は `src/`, `bin/`, `bootstrap.php` です。テスト・分析スクリプト・比較用コードは、本体の合計に混ぜず扱います。
