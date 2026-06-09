#!/usr/bin/env bash
#
# Fast pre-push guard for ParkHub local-first CI.
#
# Git opens the remote receive-pack connection before running pre-push hooks.
# A long hook that starts the full nido gate can therefore leave github.com idle
# long enough for the SSH session to close before the push begins. Keep this
# hook fast: require a success report for the current HEAD, and run `make ci`
# before `git push` when the report is missing or stale.
#
# Report path resolution (nido-first, fop compat):
#   1. .nido/reports/local-ci-<profile>-<sha>.json  (canonical, nido-local-ci.sh)
#   2. .fop/reports/local-ci-<profile>-<sha>.json   (compat, older runs)
# The first file found wins; both context values (nido/local-ci/pr and
# fop/local-ci/pr) are accepted.

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

# Resolve canonical path first (.nido/), then compat path (.fop/).
nido_report=".nido/reports/local-ci-${profile}-${sha}.json"
fop_report=".fop/reports/local-ci-${profile}-${sha}.json"

if [[ -f "$nido_report" ]]; then
  report="$nido_report"
elif [[ -f "$fop_report" ]]; then
  report="$fop_report"
else
  report="$nido_report"  # canonical path used in the error message
fi

fail() {
  echo "ERROR: $*" >&2
  echo "Run: make ci" >&2
  exit 1
}

if [[ ! -f "$report" ]]; then
  fail "missing local CI success report for ${sha:0:8} (checked ${nido_report} and ${fop_report})"
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
// Accept both nido and fop context values (transition period).
$nido_context = "nido/local-ci/" . $profile;
$fop_context  = "fop/local-ci/" . $profile;
$expected = [
    "profile" => $profile,
    "state" => "success",
    "commit" => $sha,
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
$ctx = $data["context"] ?? null;
if ($ctx !== $nido_context && $ctx !== $fop_context) {
    fwrite(STDERR, "ERROR: report field context expected " . $nido_context . " or " . $fop_context . "\n");
    exit(1);
}
' "$report" "$sha" "$profile" || fail "local CI report is not a success report for ${sha:0:8}"

echo "Local CI report OK: ${report}"
