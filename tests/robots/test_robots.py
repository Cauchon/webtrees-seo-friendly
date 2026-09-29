"""Check generated robots.txt against representative webtrees URL forms."""

from __future__ import annotations

import re
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TEMPLATE = ROOT / "resources/views/robots-txt.phtml"
TRAINING_AGENTS = (
    "Google-Extended", "GPTBot", "ClaudeBot", "anthropic-ai",
    "MistralAI-Training", "meta-externalagent", "FacebookBot",
    "CCBot", "Bytespider", "AI2Bot", "Ai2Bot-Dolma",
    "cohere-training-data-crawler", "Amazonbot", "Applebot-Extended",
)
BLOCKED_ROUTES = (
    "/tree/example-tree/calendar/day",
    "/tree/example-tree/calendar-events/month",
    "/tree/example-tree/report/cemetery_report",
    "/tree/example-tree/report-run/cemetery_report",
    "/tree/example-tree/search-general",
    "/tree/example-tree/fan-chart-3-4-100/I123",
    "/tree/example-tree/family-book-2-5-0/I123",
    "/tree/example-tree/timeline-10/I123",
    "/module/statistics_chart/Chart/example-tree",
)
ALLOWED_ROUTES = (
    "/tree/example-tree",
    "/tree/example-tree/individual/I123/alex-example",
    "/tree/example-tree/family/F456/jordan-example-pat-example",
    "/tree/example-tree/record/I123",
    "/sitemap.xml",
)


def render_template(base_path: str = "") -> str:
    php = (
        "$bad_user_agents = ['GPTBot', 'ClaudeBot', 'Google-Extended'];"
        "$base_path = " + repr(base_path) + ";"
        "$base_url = 'https://example.org';"
        "$sitemap_url = 'https://example.org/index.php?route=/sitemap.xml';"
        "$trees = ['example-tree'];"
        "include " + repr(str(TEMPLATE)) + ";"
    )
    return subprocess.run(
        ["php", "-r", php], check=True, capture_output=True, text=True
    ).stdout


def render_effective_template() -> str:
    php = (
        "namespace Psr\\Http\\Server { "
        "interface MiddlewareInterface { public function process("
        "\\Psr\\Http\\Message\\ServerRequestInterface $request, "
        "RequestHandlerInterface $handler): "
        "\\Psr\\Http\\Message\\ResponseInterface; } "
        "interface RequestHandlerInterface {} } "
        "namespace { "
        "require " + repr(str(ROOT / "app/Http/Middleware/BadBotBlocker.php")) + ";"
        "require " + repr(str(ROOT / "app/Http/Middleware/CauchonBotPolicy.php")) + ";"
        "$bad_user_agents = "
        "\\Fisharebest\\Webtrees\\Http\\Middleware\\CauchonBotPolicy::blockedRobots();"
        "$base_path = '';"
        "$base_url = 'https://example.org';"
        "$sitemap_url = 'https://example.org/index.php?route=/sitemap.xml';"
        "$trees = ['example-tree'];"
        "include " + repr(str(TEMPLATE)) + "; }"
    )
    return subprocess.run(
        ["php", "-r", php], check=True, capture_output=True, text=True
    ).stdout


def groups(text: str) -> list[tuple[list[str], list[str], list[str]]]:
    result: list[tuple[list[str], list[str], list[str]]] = []
    agents: list[str] = []
    disallows: list[str] = []
    delays: list[str] = []
    for raw in text.splitlines():
        line = raw.partition("#")[0].strip()
        if not line or ":" not in line:
            continue
        key, value = (part.strip() for part in line.split(":", 1))
        key = key.lower()
        if key == "user-agent":
            if disallows or delays:
                result.append((agents, disallows, delays))
                agents, disallows, delays = [], [], []
            agents.append(value.lower())
        elif key == "disallow":
            disallows.append(value)
        elif key == "crawl-delay":
            delays.append(value)
    if agents:
        result.append((agents, disallows, delays))
    return result


def blocked(text: str, agent: str, url: str) -> bool:
    parsed = groups(text)
    ua = agent.lower()
    matching = [group for group in parsed if any(a != "*" and a in ua for a in group[0])]
    if matching:
        # Longest matching named token takes precedence over the wildcard group.
        max_length = max(len(a) for agents, _, _ in matching for a in agents if a != "*" and a in ua)
        applicable = [group for group in matching if any(len(a) == max_length and a in ua for a in group[0])]
    else:
        applicable = [group for group in parsed if "*" in group[0]]
    for _, disallows, _ in applicable:
        for rule in disallows:
            pattern = "^" + re.escape(rule).replace(r"\*", ".*")
            if re.search(pattern, url):
                return True
    return False


class RobotsRulesTest(unittest.TestCase):
    def test_effective_policy_rules(self) -> None:
        text = render_effective_template()
        self.check_public_routes(text)
        self.assertIn("Sitemap: https://example.org/index.php?route=/sitemap.xml", text)
        self.assertFalse(blocked(text, "Applebot", "/tree/example-tree/individual/I123"))
        self.assertTrue(blocked(text, "Applebot", "/tree/example-tree/calendar/day"))
        named_agents = {agent for agents, _, _ in groups(text) for agent in agents}
        for agent in TRAINING_AGENTS:
            self.assertIn(agent.lower(), named_agents)
            self.assertTrue(blocked(text, agent, "/tree/example-tree/individual/I123"))
            self.assertTrue(blocked(text, agent, "/index.php?route=/sitemap.xml"))

    def test_generated_rules(self) -> None:
        text = render_template()
        self.check_public_routes(text)
        self.assertTrue(blocked(text, "Applebot-Extended", "/tree/example-tree/individual/I123"))
        self.assertTrue(blocked(text, "GPTBot", "/index.php?route=/sitemap.xml"))
        self.assertIn("Sitemap: https://example.org/index.php?route=/sitemap.xml", text)

    def test_generated_rules_with_subdirectory(self) -> None:
        text = render_template("/webtrees")
        self.assertTrue(blocked(text, "Claude-SearchBot", "/webtrees/index.php?route=%2Ftree%2Fexample-tree%2Fcalendar%2Fday"))
        self.assertFalse(blocked(text, "Claude-SearchBot", "/webtrees/index.php?route=%2Ftree%2Fexample-tree%2Findividual%2FI123"))

    def check_public_routes(self, text: str) -> None:
        wildcard = next(group for group in groups(text) if "*" in group[0])
        self.assertEqual(wildcard[2], ["10"])
        for route in BLOCKED_ROUTES:
            encoded = "/index.php?route=" + route.replace("/", "%2F")
            plain = "/index.php?route=" + route
            for url in (route, plain, encoded, "/index.php?foo=x&route=" + route, "/index.php?foo=x&route=" + route.replace("/", "%2F")):
                with self.subTest(url=url):
                    self.assertTrue(blocked(text, "Claude-SearchBot", url))
                    self.assertTrue(blocked(text, "Googlebot", url))
        for route in ALLOWED_ROUTES:
            encoded = "/index.php?route=" + route.replace("/", "%2F")
            plain = "/index.php?route=" + route
            for url in (route, plain, encoded, "/index.php?foo=x&route=" + route, "/index.php?foo=x&route=" + route.replace("/", "%2F")):
                with self.subTest(url=url):
                    self.assertFalse(blocked(text, "Claude-SearchBot", url))
                    self.assertFalse(blocked(text, "Googlebot", url))


if __name__ == "__main__":
    unittest.main()
