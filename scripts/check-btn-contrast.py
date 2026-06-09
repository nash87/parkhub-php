#!/usr/bin/env python3
"""Static WCAG AA contrast gate for .btn-primary in parkhub-web.

Caught live: white text on --color-primary-700 (#ab7220) = 4.07:1 < 4.5:1
(axe color-contrast, serious) after the securanido token vendoring. This
gate resolves the btn-primary bg/fg var chains against the vendored palette
and the v5 bridge tokens and fails if any mode pair drops below AA for
normal-size text. Pure stdlib; no browser needed, so it runs in local CI.

Mirrors the parkhub-rust fix in design-v5-warm commit 598765d5.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
GLOBAL_CSS = REPO / "parkhub-web/src/styles/global.css"
BRIDGE_CSS = REPO / "parkhub-web/src/design-v5/main-app-bridge.css"
VENDOR_CSS = REPO / "parkhub-web/src/design-v5/vendor/nido-securanido.css"

AA_NORMAL = 4.5

VAR_DECL = re.compile(r"(--[\w-]+)\s*:\s*([^;]+);")
HEX_RE = re.compile(r"#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b")

NAMED = {"white": "#ffffff", "black": "#000000"}


def parse_vars(*files: Path) -> dict[str, str]:
    table: dict[str, str] = {}
    for f in files:
        for name, value in VAR_DECL.findall(f.read_text()):
            # First definition wins per file order (vendor :root first, bridge
            # light-mode :root, then dark-mode blocks are handled separately).
            table.setdefault(name, value.strip())
    return table


def parse_dark_vars(bridge: Path) -> dict[str, str]:
    """Token overrides from the bridge's dark/void blocks (best effort)."""
    text = bridge.read_text()
    dark: dict[str, str] = {}
    for block in re.findall(r"[^{}]*dark[^{}]*\{([^}]*)\}", text, re.IGNORECASE):
        for name, value in VAR_DECL.findall(block):
            dark[name] = value.strip()
    return dark


def resolve(value: str, table: dict[str, str], depth: int = 0) -> str | None:
    if depth > 6:
        return None
    value = value.strip()
    if value in NAMED:
        return NAMED[value]
    m = HEX_RE.search(value)
    if m:
        h = m.group(1)
        if len(h) == 3:
            h = "".join(c * 2 for c in h)
        return "#" + h.lower()
    var = re.match(r"var\(\s*(--[\w-]+)\s*(?:,\s*(.+))?\)", value)
    if var:
        name, fallback = var.group(1), var.group(2)
        if name in table:
            resolved = resolve(table[name], table, depth + 1)
            if resolved:
                return resolved
        if fallback:
            return resolve(fallback, table, depth + 1)
    return None


def luminance(hex_color: str) -> float:
    rgb = [int(hex_color[i : i + 2], 16) / 255 for i in (1, 3, 5)]
    lin = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in rgb]
    return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2]


def contrast(fg: str, bg: str) -> float:
    l1, l2 = sorted((luminance(fg), luminance(bg)), reverse=True)
    return (l1 + 0.05) / (l2 + 0.05)


def btn_primary_pair(css: Path) -> tuple[str, str]:
    text = css.read_text()
    m = re.search(r"btn-primary\s*\{([^}]*)\}", text)
    if not m:
        sys.exit(f"FAIL: no btn-primary rule found in {css}")
    body = m.group(1)
    bg = re.search(r"background\s*:\s*([^;]+);", body)
    fg = re.search(r"color\s*:\s*([^;]+);", body)
    if not bg or not fg:
        sys.exit(f"FAIL: btn-primary in {css} lacks explicit background/color")
    return bg.group(1).strip(), fg.group(1).strip()


def main() -> int:
    base = parse_vars(VENDOR_CSS, BRIDGE_CSS)
    dark_overrides = parse_dark_vars(BRIDGE_CSS)
    bg_raw, fg_raw = btn_primary_pair(GLOBAL_CSS)

    failures = []
    for mode, table in (
        ("light", base),
        ("dark", {**base, **dark_overrides}),
    ):
        bg = resolve(bg_raw, table)
        fg = resolve(fg_raw, table)
        if bg is None or fg is None:
            failures.append(f"{mode}: cannot resolve pair bg={bg_raw!r} fg={fg_raw!r}")
            continue
        ratio = contrast(fg, bg)
        status = "OK " if ratio >= AA_NORMAL else "FAIL"
        print(f"{status} btn-primary [{mode}] {fg} on {bg} = {ratio:.3f}:1 (AA needs {AA_NORMAL}:1)")
        if ratio < AA_NORMAL:
            failures.append(f"{mode}: {ratio:.3f}:1 < {AA_NORMAL}:1 ({fg} on {bg})")

    failures.extend(check_amber_text_on_light())

    if failures:
        print("FAIL: .btn-primary violates WCAG AA contrast:", file=sys.stderr)
        for f in failures:
            print(f"  - {f}", file=sys.stderr)
        return 1
    print("ParkHub btn-primary contrast contract OK.")
    return 0




def check_amber_text_on_light() -> list[str]:
    """text-primary-700 on light surfaces must meet AA.

    The palette's --color-primary-700 (#ab7220) reads 4.01:1 on white —
    axe caught it on the login links (parkhub-rust #679). The fix is a
    light-mode-scoped @layer utilities override mapping the utility to
    --color-primary-800. This check computes the EFFECTIVE light-mode
    color of .text-primary-700 (override if present, else palette) and
    fails below AA on white.
    """
    table = parse_vars(VENDOR_CSS, BRIDGE_CSS)
    css = GLOBAL_CSS.read_text()
    m = re.search(
        r":root:not\(\.dark\)\s+\.text-primary-700\s*\{[^}]*color\s*:\s*([^;]+);",
        css,
    )
    effective_raw = m.group(1).strip() if m else "var(--color-primary-700)"
    fg = resolve(effective_raw, table)
    if fg is None:
        return [f"text-primary-700: cannot resolve effective color {effective_raw!r}"]
    ratio = contrast(fg, "#ffffff")
    status = "OK " if ratio >= AA_NORMAL else "FAIL"
    print(f"{status} text-primary-700 [light, on white] {fg} = {ratio:.3f}:1 (AA needs {AA_NORMAL}:1)")
    if ratio < AA_NORMAL:
        return [f"text-primary-700 light: {ratio:.3f}:1 < {AA_NORMAL}:1 ({fg} on #ffffff)"]
    return []

if __name__ == "__main__":
    raise SystemExit(main())
