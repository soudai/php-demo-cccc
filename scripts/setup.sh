#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

if ! command -v php >/dev/null; then
    echo 'PHP is missing. On Ubuntu: sudo apt-get update && sudo apt-get install -y php-cli' >&2
    exit 1
fi
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || {
    echo 'PHP 8.2 or newer is required.' >&2
    exit 1
}

version='v1.7.0'
case "$(uname -m)" in
    x86_64)
        target='x86_64-unknown-linux-musl'
        checksum='75d2fb5486238eeb40a80fec2de8fe5075e6ce567b6741a56fa511ed2082e45a'
        ;;
    aarch64)
        target='aarch64-unknown-linux-musl'
        checksum='f775a19ebd58fc7a4cd64769b8d442425d8afde66207386db04eb87747ed75b8'
        ;;
    *) echo 'Unsupported architecture.' >&2; exit 1 ;;
esac

destination='.tools/cccc-linux'
mkdir -p "$destination"
if [[ ! -x "$destination/cccc" ]] || [[ "$("$destination/cccc" --version)" != "cccc ${version#v}" ]]; then
    archive=".tools/cccc-${version}-${target}.tar.gz"
    curl --fail --location --retry 3 \
        "https://github.com/moznion/cccc/releases/download/${version}/cccc-${version}-${target}.tar.gz" \
        --output "$archive"
    printf '%s  %s\n' "$checksum" "$archive" | sha256sum --check -
    tar -xzf "$archive" -C "$destination"
    chmod +x "$destination/cccc"
fi
php --version
"$destination/cccc" --version
echo 'Ready: bash scripts/demo.sh'
