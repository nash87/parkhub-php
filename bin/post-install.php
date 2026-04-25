<?php

declare(strict_types=1);

if (is_file(__DIR__.'/../vendor/bin/lefthook')) {
    passthru(escapeshellarg(__DIR__.'/../vendor/bin/lefthook').' install');
    exit(0);
}

$rc = 0;
passthru('lefthook install 2>/dev/null', $rc);

if ($rc !== 0) {
    fwrite(STDERR, "note: lefthook not found; install it from https://lefthook.dev to enable local git hooks\n");
}
