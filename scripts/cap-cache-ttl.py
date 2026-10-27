#!/usr/bin/env python3
"""Cap Cache::remember TTLs at 60 minutes so cached signed storage URLs never outlive their signature.

Usage: python3 scripts/cap-cache-ttl.py app
Rewrites literal TTLs longer than an hour and lists TTLs it cannot evaluate (variables,
constants) so they can be checked by hand.
"""
import re
import sys
from pathlib import Path

NEW = "now()->addMinutes(60)"
CAP = 3600
UNITS = {"Seconds": 1, "Minutes": 60, "Hours": 3600, "Days": 86400, "Weeks": 604800, "Months": 2592000, "Years": 31536000}


def split_args(s, start):
    """Return (list of (arg_start, arg_end)) for call whose '(' is at s[start]."""
    depth = 0
    i = start
    args = []
    arg_start = start + 1
    quote = None
    while i < len(s):
        c = s[i]
        if quote:
            if c == "\\":
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in "'\"":
            quote = c
        elif c in "([{":
            depth += 1
        elif c in ")]}":
            depth -= 1
            if depth == 0:
                args.append((arg_start, i))
                return args
        elif c == "," and depth == 1:
            args.append((arg_start, i))
            arg_start = i + 1
        i += 1
    raise ValueError("unbalanced")


def seconds(expr):
    e = expr.strip()
    if re.fullmatch(r"\d+", e):
        return int(e)
    m = re.fullmatch(r"now\(\)->add(Seconds|Minutes|Hours|Days|Weeks|Months|Years)\((\d*)\)", e)
    if m:
        return UNITS[m.group(1)] * int(m.group(2) or 1)
    m = re.fullmatch(r"now\(\)->add(Second|Minute|Hour|Day|Week|Month|Year)\(\)", e)
    if m:
        return UNITS[m.group(1) + "s"]
    return None


def process(path, unresolved):
    src = path.read_text()
    out = []
    pos = 0
    changed = False
    for m in re.finditer(r"Cache::remember\(", src):
        paren = m.end() - 1
        if paren < pos:
            continue
        args = split_args(src, paren)
        if len(args) < 3:
            continue
        a, b = args[1]
        raw = src[a:b]
        val = seconds(raw)
        line = src.count("\n", 0, a) + 1
        if val is None:
            unresolved.append(f"{path}:{line}: {raw.strip()}")
            continue
        if val <= CAP:
            continue
        lead = raw[: len(raw) - len(raw.lstrip())]
        trail = raw[len(raw.rstrip()):]
        out.append(src[pos:a] + lead + NEW + trail)
        pos = b
        changed = True
    if changed:
        out.append(src[pos:])
        path.write_text("".join(out))
    return changed


def main():
    unresolved = []
    n = 0
    for p in sorted(Path(sys.argv[1]).rglob("*.php")):
        if process(p, unresolved):
            n += 1
    print(f"changed {n} files")
    print("unresolved:")
    print("\n".join(unresolved))


main()
