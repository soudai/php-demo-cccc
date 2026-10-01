[CmdletBinding()]
param(
    [ValidateSet('setup', 'play', 'demo', 'test', 'analyze')]
    [string]$Action = 'play',
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$GameArguments = @()
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $projectRoot
try {
    switch ($Action) {
        'setup' { & wsl -d Ubuntu-24.04 -- bash scripts/setup.sh }
        'play' { & wsl -d Ubuntu-24.04 -- php bin/othello.php @GameArguments }
        'demo' { & wsl -d Ubuntu-24.04 -- bash scripts/demo.sh }
        'test' { & wsl -d Ubuntu-24.04 -- php tests/run.php }
        'analyze' { & wsl -d Ubuntu-24.04 -- php scripts/analyze.php }
    }
    $result = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $result
