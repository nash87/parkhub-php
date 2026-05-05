#!/usr/bin/env bash
#
# Fast pre-push guard for ParkHub local-first CI.
#
# Git opens the remote receive-pack connection before running pre-push hooks.
# A long hook that starts the full fop gate can therefore leave github.com idle
# long enough for the SSH session to close before the push begins. Keep this
# hook fast: require a success report for the current HEAD, and run `make ci`
# before `git push` when the report is missing or stale.

set -euo pipefail

profile="${1:-pr}"
case "$profile" in
  pr|full|cd) ;;
  *)
    echo "ERROR: profile must be one of: pr full cd (got: $profile)" >&2
    exit 2
    ;;
esac

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

sha="$(git rev-parse HEAD)"
report=".fop/reports/local-ci-${profile}-${sha}.json"

fail() {
  echo "ERROR: $*" >&2
  echo "Run: make ci" >&2
  exit 1
}

if [[ ! -f "$report" ]]; then
  fail "missing local CI success report for ${sha:0:8} (${report})"
fi

php -r '
$path = $argv[1];
$sha = $argv[2];
$profile = $argv[3];
$data = json_decode(file_get_contents($path), true);
if (!is_array($data)) {
    fwrite(STDERR, "ERROR: report is not valid JSON\n");
    exit(1);
}
$expected = [
    "profile" => $profile,
    "state" => "success",
    "commit" => $sha,
    "context" => "fop/local-ci/" . $profile,
];
if (!in_array(($data["schema"] ?? null), ["parkhub.local-ci.v1", "parkhub.local-ci.v2"], true)) {
    fwrite(STDERR, "ERROR: report field schema expected parkhub.local-ci.v1 or parkhub.local-ci.v2\n");
    exit(1);
}
foreach ($expected as $key => $value) {
    if (($data[$key] ?? null) !== $value) {
        fwrite(STDERR, "ERROR: report field " . $key . " expected " . $value . "\n");
        exit(1);
    }
}
' "$report" "$sha" "$profile" || fail "local CI report is not a success report for ${sha:0:8}"

echo "Local CI report OK: ${report}"
