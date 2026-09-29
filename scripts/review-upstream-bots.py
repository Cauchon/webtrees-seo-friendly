#!/usr/bin/env python3
"""Report upstream BAD_ROBOTS changes for a human PR review."""

from __future__ import annotations

import argparse
import re
import subprocess
from pathlib import Path

BOT_FILE = "app/Http/Middleware/BadBotBlocker.php"
POLICY_FILE = "app/Http/Middleware/CauchonBotPolicy.php"
APPROVED_USER_AGENTS = {
    "Google Search inspection": "Google-InspectionTool/1.0",
    "Google image search": "Googlebot-Image/1.0",
    "Apple search": "Applebot/0.1",
    "Yandex search": "Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)",
    "Bing preview": "BingPreview/1.0",
    "DuckDuckGo AI answers": "DuckAssistBot/1.2; (+http://duckduckgo.com/duckassistbot.html)",
    "ChatGPT search": "OAI-SearchBot/1.0 (+https://openai.com/searchbot)",
    "ChatGPT user fetch": "ChatGPT-User/1.0 (+https://openai.com/bot)",
    "Claude search": "Claude-SearchBot/1.0",
    "Claude user fetch": "Claude-User/1.0",
    "Perplexity search": "PerplexityBot/1.0",
    "Perplexity user fetch": "Perplexity-User/1.0",
    "Mistral user fetch": "MistralAI-User/1.0",
    "Discord preview": "Discordbot/2.0",
    "Slack preview": "Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)",
    "Facebook preview": "facebookexternalhit/1.1",
}


def git(*args: str) -> str:
    return subprocess.check_output(["git", *args], text=True).strip()


def php_tokens(source: str, anchor: str, ending: str) -> set[str]:
    if anchor not in source:
        raise ValueError(f"Could not find {anchor}")
    body = source.split(anchor, 1)[1].split(ending, 1)[0]
    tokens = set(re.findall(r"^        '((?:\\.|[^'])+)'", body, re.M))
    if not tokens:
        raise ValueError(f"No tokens found after {anchor}")
    return {token.replace("\\'", "'").replace("\\\\", "\\") for token in tokens}


def md(value: str) -> str:
    return "`" + value.replace("|", "\\|").replace("`", "\\`") + "`"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base", help="last shared upstream commit; defaults to merge-base of main and upstream/main")
    parser.add_argument("--head", default="upstream/main", help="incoming upstream ref")
    args = parser.parse_args()

    base = args.base or git("merge-base", "main", args.head)
    base_sha = git("rev-parse", base)
    head_sha = git("rev-parse", args.head)
    before = php_tokens(git("show", f"{base_sha}:{BOT_FILE}"), "public const array BAD_ROBOTS = [", "    ];")
    after = php_tokens(git("show", f"{head_sha}:{BOT_FILE}"), "public const array BAD_ROBOTS = [", "    ];")
    policy = Path(POLICY_FILE).read_text()
    exceptions = php_tokens(policy, "private const array PUBLIC_ROBOT_EXCEPTIONS = [", "    ];")
    added = sorted(after - before, key=str.casefold)
    removed = sorted(before - after, key=str.casefold)

    print("# Upstream bot-list review")
    print()
    print(f"- Last shared upstream commit: {md(base_sha)}")
    print(f"- Incoming upstream commit: {md(head_sha)}")
    print(f"- New `BAD_ROBOTS` entries: **{len(added)}**")
    print(f"- Removed `BAD_ROBOTS` entries: **{len(removed)}**")
    print()
    print("## New blocked tokens — decide each before merging")
    print()
    if added:
        print("| Upstream token | Overlaps an approved agent | Decision |")
        print("| --- | --- | --- |")
        for token in added:
            overlaps = [name for name, ua in APPROVED_USER_AGENTS.items() if token in ua]
            if token in exceptions and not overlaps:
                overlaps = ["Already in local exceptions"]
            effect = ", ".join(overlaps) if overlaps else "No known overlap; review purpose"
            print(f"| {md(token)} | {effect} | Pending owner review |")
    else:
        print("No new upstream block-list entries.")
    print()
    print("## Removed upstream tokens")
    print()
    if removed:
        for token in removed:
            print(f"- {md(token)}")
    else:
        print("No upstream block-list removals.")
    print()
    print("## Related upstream files changed")
    print()
    related = git("diff", "--name-only", base_sha, head_sha, "--", BOT_FILE,
                  "app/Http/Controllers/RobotsTxt.php", "app/Http/RequestHandlers/RobotsTxt.php",
                  "resources/views/robots-txt.phtml")
    if related:
        for path in related.splitlines():
            print(f"- {md(path)}")
    else:
        print("None of the bot middleware or robots.txt files changed.")
    print()
    print("## Review gate")
    print()
    print("For each new token, check the operator's current documentation and intended use; classify it as search, AI search, user-requested fetch, link preview, training, or other. Check substring overlaps with allowed agents, verify the generated robots.txt and middleware stay aligned, and record the decision in this PR. Do not merge until the site owner has reviewed the decisions and explicitly approved the PR.")


if __name__ == "__main__":
    main()
