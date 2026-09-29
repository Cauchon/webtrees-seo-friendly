"""Offline release-sync smoke tests with local Git remotes and a fake gh CLI."""

from __future__ import annotations

import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

SOURCE = Path(__file__).resolve().parents[2]
BOT = """<?php
final class BadBotBlocker {
    public const array BAD_ROBOTS = [
        'old-bot',
    ];
    public function check($ua) { return CauchonBotPolicy::isBlocked($ua); }
}
"""
POLICY = """<?php
final class CauchonBotPolicy {
    private const array PUBLIC_ROBOT_EXCEPTIONS = [
        'allowed-bot',
    ];
}
"""
FAKE_GH = """#!/bin/sh
case "$1 $2" in
  'repo view') echo 'Cauchon/webtrees-less-restrictive' ;;
  'api --paginate') printf '%s\\n' "$MOCK_RELEASES" ;;
  'pr list') printf '%s\\n' "${MOCK_PR_STATE:-}" ;;
  'pr create') printf '%s\\n' "$*" >> "$MOCK_GH_LOG" ;;
  *) echo "unexpected gh call: $*" >&2; exit 2 ;;
esac
"""


def run(args: list[str], cwd: Path, env: dict[str, str] | None = None, check: bool = True) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(args, cwd=cwd, env=env, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if check and result.returncode:
        raise AssertionError(f"{args!r} failed ({result.returncode}): {result.stderr}\n{result.stdout}")
    return result


class StableSyncTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.upstream = self.root / "upstream"
        self.fork = self.root / "fork"
        self.origin = self.root / "origin.git"
        self.bin = self.root / "bin"
        self.bin.mkdir()
        (self.bin / "gh").write_text(FAKE_GH)
        (self.bin / "php").write_text("#!/bin/sh\nexit 0\n")
        for name in ("gh", "php"):
            (self.bin / name).chmod(0o755)
        run(["git", "init", "-q", "-b", "main", str(self.upstream)], self.root)
        run(["git", "config", "user.name", "Test"], self.upstream)
        run(["git", "config", "user.email", "test@example.org"], self.upstream)
        self.write(self.upstream, "app/Http/Middleware/BadBotBlocker.php", BOT)
        self.write(self.upstream, "app/Http/RequestHandlers/RobotsTxt.php", "<?php\nCauchonBotPolicy::blockedRobots();\n")
        run(["git", "add", "."], self.upstream)
        run(["git", "commit", "-qm", "2.2.6"], self.upstream)
        run(["git", "tag", "2.2.6"], self.upstream)
        run(["git", "clone", "-q", str(self.upstream), str(self.fork)], self.root)
        run(["git", "config", "user.name", "Test"], self.fork)
        run(["git", "config", "user.email", "test@example.org"], self.fork)
        run(["git", "remote", "rename", "origin", "upstream"], self.fork)
        self.write(self.fork, "app/Http/Middleware/CauchonBotPolicy.php", POLICY)
        self.write(self.fork, "tests/app/Http/Middleware/CauchonBotPolicyTest.php", "<?php\n")
        for name in ("prepare-upstream-sync.sh", "publish-upstream-sync.sh", "select-stable-release.py", "review-upstream-bots.py"):
            destination = self.fork / "scripts" / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(SOURCE / "scripts" / name, destination)
        run(["git", "add", "."], self.fork)
        run(["git", "commit", "-qm", "fork policy"], self.fork)
        run(["git", "clone", "-q", "--bare", str(self.fork), str(self.origin)], self.root)
        run(["git", "remote", "add", "origin", str(self.origin)], self.fork)
        self.out = self.root / "out"
        self.log = self.root / "gh.log"
        self.env = os.environ.copy()
        self.env.update(PATH=f"{self.bin}:{os.environ['PATH']}", MOCK_RELEASES="2.2.6", MOCK_GH_LOG=str(self.log), SYNC_OUTPUT_DIR=str(self.out))

    @staticmethod
    def write(repo: Path, name: str, content: str) -> None:
        path = repo / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content)

    def release(self, conflict: bool = False) -> None:
        bot = BOT.replace("old-bot", "new-bot") if conflict else BOT.replace("    ];", "        'new-bot',\n    ];")
        self.write(self.upstream, "app/Http/Middleware/BadBotBlocker.php", bot)
        run(["git", "add", "."], self.upstream)
        run(["git", "commit", "-qm", "2.2.7"], self.upstream)
        run(["git", "tag", "2.2.7"], self.upstream)
        self.env["MOCK_RELEASES"] = "2.2.6\n2.2.7\nv2.2.7-rc1"

    def prepare(self) -> subprocess.CompletedProcess[str]:
        return run(["bash", "scripts/prepare-upstream-sync.sh", "--prepare-only"], self.fork, self.env, check=False)

    def test_no_new_stable_release(self) -> None:
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.out / "status").read_text().strip(), "no-update")
        self.assertEqual(run(["git", "branch", "--show-current"], self.fork).stdout.strip(), "main")

    def test_prepares_highest_numeric_stable_as_draft(self) -> None:
        self.release()
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.out / "status").read_text().strip(), "prepared")
        self.assertEqual((self.out / "tag").read_text().strip(), "2.2.7")
        self.assertIn("published stable webtrees release `2.2.7`", (self.out / "pr-body.md").read_text())
        self.assertTrue((self.out / "sync.bundle").is_file())
        self.assertFalse(self.log.exists())

    def test_existing_pr_is_idempotent(self) -> None:
        self.release()
        self.env["MOCK_PR_STATE"] = "OPEN"
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.out / "status").read_text().strip(), "existing-pr")

    def test_merge_conflict_fails_without_publication(self) -> None:
        self.write(self.fork, "app/Http/Middleware/BadBotBlocker.php", BOT.replace("old-bot", "local-bot"))
        run(["git", "add", "."], self.fork)
        run(["git", "commit", "-qm", "local bot change"], self.fork)
        self.release(conflict=True)
        result = self.prepare()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("Merge conflicts", result.stderr)
        self.assertFalse(self.log.exists())

    def test_does_not_downgrade_newer_local_baseline(self) -> None:
        self.release()
        run(["git", "tag", "2.2.8"], self.fork)
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.out / "status").read_text().strip(), "no-update")

    def test_missing_policy_callsite_fails(self) -> None:
        self.release()
        self.write(self.fork, "app/Http/RequestHandlers/RobotsTxt.php", "<?php\n")
        run(["git", "add", "."], self.fork)
        run(["git", "commit", "-qm", "disconnect robots policy"], self.fork)
        result = self.prepare()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("no longer calls CauchonBotPolicy::blockedRobots", result.stderr)

    def test_conflicting_remote_branch_is_not_replaced(self) -> None:
        self.release()
        run(["git", "push", "origin", "main:refs/heads/sync/upstream-2.2.7"], self.fork)
        result = self.prepare()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("does not contain main and stable", result.stderr)
        self.assertFalse(self.log.exists())

    def test_retry_after_push_creates_missing_pr(self) -> None:
        self.release()
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        run(["git", "push", "origin", "HEAD:refs/heads/sync/upstream-2.2.7"], self.fork)
        run(["git", "switch", "main"], self.fork)
        run(["git", "branch", "-D", "sync/upstream-2.2.7"], self.fork)
        shutil.rmtree(self.out)
        result = self.prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.out / "status").read_text().strip(), "prepared")
        run(["git", "switch", "main"], self.fork)
        result = run(["bash", "scripts/publish-upstream-sync.sh", str(self.out)], self.fork, self.env, check=False)
        self.assertEqual(result.returncode, 0, result.stderr)
        created = self.log.read_text().strip()
        self.assertIn('--repo Cauchon/webtrees-less-restrictive', created)
        self.assertIn('--draft', created)


if __name__ == "__main__":
    unittest.main()
