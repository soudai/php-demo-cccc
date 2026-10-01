<?php

declare(strict_types=1);

/** @return list<array<string, mixed>> */
function functionsIn(array $report): array
{
    $functions = [];
    foreach ($report['files'] as $file) {
        foreach ($file['functions'] as $function) {
            $functions[] = ['path' => $file['path'], ...$function];
        }
    }
    return $functions;
}

function metricTotals(array $functions): array
{
    return [
        'count' => count($functions),
        'cognitive_sum' => array_sum(array_column($functions, 'cognitive')),
        'cognitive_max' => max(array_column($functions, 'cognitive')),
        'cyclomatic_sum' => array_sum(array_column($functions, 'cyclomatic')),
        'cyclomatic_max' => max(array_column($functions, 'cyclomatic')),
    ];
}

function escape(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array{markdown: string, html: string} */
function buildReport(array $application, array $baseline, array $metadata): array
{
    $functions = functionsIn($application);
    usort($functions, static fn (array $a, array $b): int =>
        [$b['cognitive'], $b['cyclomatic']] <=> [$a['cognitive'], $a['cyclomatic']]);
    $baselineFunction = functionsIn($baseline)[0];
    $helperNames = ['legalMoves', 'flipsAt', 'scanDirection', 'inside', 'opponent', 'coordinate'];
    $helpers = array_values(array_filter($functions, static fn (array $function): bool =>
        str_replace('\\', '/', $function['path']) === 'src/Board.php'
        && in_array($function['name'], $helperNames, true)));
    if (count($helpers) !== count($helperNames)) {
        throw new RuntimeException('比較対象のBoardメソッドが変わりました。report.phpの比較範囲を更新してください。');
    }
    $helperTotals = metricTotals($helpers);
    $main = array_values(array_filter($helpers, static fn (array $f): bool => $f['name'] === 'legalMoves'))[0];
    $summary = $application['summary'];
    $gatePassed = $metadata['application']['exit_code'] === 0;
    $baselinePassed = $metadata['baseline']['exit_code'] === 0;
    $status = $gatePassed ? 'PASS' : 'FAIL';
    $baselineStatus = $baselinePassed ? 'PASS' : 'FAIL（比較用の想定結果）';
    $limitCognitive = $metadata['thresholds']['cognitive'];
    $limitCyclomatic = $metadata['thresholds']['cyclomatic'];
    $markdown = "# PHP Othello / CCCC 実測レポート\n\n"
        . "計測日時: {$metadata['generated_at']} / {$metadata['cccc_version']} / PHP {$metadata['php_version']} / {$metadata['platform']}\n\n"
        . "対象: `src/`, `bin/`, `bootstrap.php`。テスト、レポート生成コード、比較用コードは本体集計の対象外。\n\n"
        . "## 本体の評価\n\n"
        . "- 品質ゲート: **$status**（関数ごとに認知的複雑度 ≤ {$limitCognitive}、循環的複雑度 ≤ {$limitCyclomatic}）\n"
        . "- {$summary['file_count']} ファイル / {$summary['function_count']} 関数 / 解析エラー {$summary['parse_error_count']}\n"
        . "- 認知的複雑度: 最大 {$summary['cognitive']['max']} / 合計 {$summary['cognitive']['sum']}\n"
        . "- 循環的複雑度: 最大 {$summary['cyclomatic']['max']} / 合計 {$summary['cyclomatic']['sum']}\n\n"
        . "閾値はこのデモの目安です。低い値だけで正しさ、保守性、性能を保証するものではありません。\n\n"
        . "## 同じ合法手判定の比較\n\n"
        . "| 範囲 | 関数数 | 認知 最大 | 認知 合計 | 循環 最大 | 循環 合計 |\n"
        . "| --- | ---: | ---: | ---: | ---: | ---: |\n"
        . "| 入れ子の多い実装 | 1 | {$baselineFunction['cognitive']} | {$baselineFunction['cognitive']} | {$baselineFunction['cyclomatic']} | {$baselineFunction['cyclomatic']} |\n"
        . "| 整理後（呼び出すヘルパー含む） | {$helperTotals['count']} | {$helperTotals['cognitive_max']} | {$helperTotals['cognitive_sum']} | {$helperTotals['cyclomatic_max']} | {$helperTotals['cyclomatic_sum']} |\n\n"
        . "入口の `legalMoves` 単体は認知 {$baselineFunction['cognitive']} → {$main['cognitive']}、循環 {$baselineFunction['cyclomatic']} → {$main['cyclomatic']}。"
        . "分割先を隠さないよう、上表では `legalMoves / flipsAt / scanDirection / inside / opponent / coordinate` を各1回集計しています。"
        . "検証済みの盤面とB/Wを入力する条件で比較しています。\n\n"
        . "整理後は盤面走査、8方向の集約、1方向の判定に役割を分け、ガード節で入れ子を減らしています。"
        . "循環的複雑度の合計には各関数の基底値と共通ヘルパーの分岐も含まれるため、関数分割で合計が増えることがあります。"
        . "合計だけで優劣を決めず、関数単位の値・責務・テスト結果を併せて評価してください。\n\n"
        . "比較用コードのゲート: **$baselineStatus**（exit {$metadata['baseline']['exit_code']}）。"
        . "本体のゲートとは分けて実行します。\n\n"
        . "## 本体の関数一覧（認知的複雑度の降順）\n\n"
        . "| 関数 | ファイル:行 | 認知 | 循環 |\n| --- | --- | ---: | ---: |\n";
    foreach ($functions as $function) {
        $markdown .= "| `{$function['name']}` | {$function['path']}:{$function['line']} | {$function['cognitive']} | {$function['cyclomatic']} |\n";
    }
    $markdown .= "\n認知的複雑度は分岐や入れ子による読み解きづらさ、循環的複雑度は制御フローの分岐を捉える指標です。"
        . "仕様: [CCCC](https://github.com/moznion/cccc/tree/v1.7.0)。生データは `application.json` と `baseline.json`、"
        . "実行条件・ソースのSHA256は `metadata.json`、テスト結果は `tests.txt`、自動対局は `game.txt` を参照してください。\n";
    ob_start();
    require __DIR__ . '/report-template.php';
    $html = ob_get_clean();
    return ['markdown' => $markdown, 'html' => $html];
}
