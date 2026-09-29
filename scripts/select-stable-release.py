#!/usr/bin/env python3
"""Select the highest published stable numeric webtrees release tag."""

import re
import sys

VERSION = re.compile(r"^(?:v)?(\d+)\.(\d+)\.(\d+)$")


def version(tag: str) -> tuple[int, int, int]:
    match = VERSION.fullmatch(tag)
    if match is None:
        raise ValueError(f"not a numeric stable release tag: {tag!r}")
    return tuple(map(int, match.groups()))


def main() -> None:
    tags = [line.strip() for line in sys.stdin if line.strip()]
    valid = [tag for tag in tags if VERSION.fullmatch(tag)]
    if not valid:
        raise SystemExit("No published stable release has a numeric major.minor.patch tag")
    print(max(valid, key=version))


if __name__ == "__main__":
    main()
