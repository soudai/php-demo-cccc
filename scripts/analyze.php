<?php

declare(strict_types=1);

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Only the game is measured. Analysis/report code and tests are outside its scope.
$root = dirname(__DIR__);
chdir($root);
require __DIR__ . '/report.php';

function execute(array $command): array
{
    $process = proc_open($command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('CCCCを起動できません。bash scripts/setup.sh を実行してください。');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
}

function saveReport(string $name, string $content): void
{
    if (file_put_contents('reports/' . $name, $content) === false) {
        throw new RuntimeException('レポートの保存に失敗しました: ' . $name);
    }
}

function analyze(string $binary, array $paths, string $name): array
{
    $arguments = ['--config', 'cccc.toml', '--pretty', ...$paths];
    $run = execute([$binary, ...$arguments]);
    if (!in_array($run['exit_code'], [0, 1], true)) {
        throw new RuntimeException("CCCC failed ({$run['exit_code']}): {$run['stderr']}\nRun: bash scripts/setup.sh");
    }
    $data = json_decode($run['stdout'], true, 512, JSON_THROW_ON_ERROR);
    if (($data['summary']['parse_error_count'] ?? -1) !== 0
        || ($data['summary']['function_count'] ?? 0) === 0) {
        throw new RuntimeException('解析エラー、または計測対象の関数がありません。');
    }
    saveReport($name . '.json', $run['stdout']);
    saveReport($name . '-gate.txt', $run['stderr']);
    return ['data' => $data, 'exit_code' => $run['exit_code'], 'arguments' => $arguments];
}

function gateLimits(): array
{
    $config = file_get_contents('cccc.toml');
    $limits = [];
    foreach (['cognitive', 'cyclomatic'] as $metric) {
        if (preg_match('/^max-' . $metric . '\s*=\s*(\d+)\s*(?:#.*)?$/m', $config, $match) !== 1) {
            throw new RuntimeException('cccc.tomlに整数の閾値 max-' . $metric . ' が必要です。');
        }
        $limits[$metric] = (int) $match[1];
    }
    return $limits;
}

try {
    if (!is_dir('reports') && !mkdir('reports', 0777, true)) {
        throw new RuntimeException('reportsディレクトリを作成できません。');
    }
    $binary = getenv('CCCC_BIN') ?: $root . '/.tools/cccc-linux/cccc';
    if (!is_file($binary) && !getenv('CCCC_BIN')) {
        $binary = 'cccc';
    }
    $version = execute([$binary, '--version']);
    if ($version['exit_code'] !== 0) {
        throw new RuntimeException('CCCCが見つかりません。bash scripts/setup.sh を実行してください。');
    }
    $application = analyze($binary, ['src', 'bin', 'bootstrap.php'], 'application');
    $baseline = analyze($binary, ['examples/baseline'], 'baseline');
    $metadata = [
        'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format(DATE_ATOM),
        'cccc_version' => trim($version['stdout']),
        'php_version' => PHP_VERSION,
        'platform' => PHP_OS_FAMILY,
        'thresholds' => gateLimits(),
        'application' => ['exit_code' => $application['exit_code'], 'arguments' => $application['arguments']],
        'baseline' => ['exit_code' => $baseline['exit_code'], 'arguments' => $baseline['arguments']],
        'source_sha256' => [],
    ];
    // Record exact inputs to make the saved measurements auditable.
    foreach (array_merge($application['data']['files'], $baseline['data']['files']) as $file) {
        $metadata['source_sha256'][$file['path']] = hash_file('sha256', $file['path']);
    }
    saveReport('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    $report = buildReport($application['data'], $baseline['data'], $metadata);
    saveReport('summary.md', $report['markdown']);
    saveReport('complexity.html', $report['html']);
    echo $report['markdown'];
    echo "\nSaved: reports/complexity.html\n";
    exit($application['exit_code']);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(2);
}
