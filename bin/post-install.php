<?php
/**
 * Composer post-install hook: install lefthook git hooks if available.
 *
 * Tries vendor/bin/lefthook first (composer-managed), then falls back to
 * lefthook on PATH (system-installed). Never fails the composer run — if
 * lefthook is missing, just prints a one-line hint.
 */

if (is_file(__DIR__ . '/../vendor/bin/lefthook')) {
    passthru(escapeshellarg(__DIR__ . '/../vendor/bin/lefthook') . ' install');
    exit(0);
}

$rc = 0;
passthru('lefthook install 2>/dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "note: lefthook not found; install it from https://lefthook.dev to enable local git hooks\n");
}
exit(0);
