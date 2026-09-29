# webtrees-less-restrictive

An independent fork of [fisharebest/webtrees](https://github.com/fisharebest/webtrees),
maintained by Justin Cauchon. It allows selected public-reading services while
retaining named training and bulk-collection restrictions.

## Stable-release baseline

The baseline is upstream **2.2.6**. Upstream updates are reviewed from published
stable releases; upstream development `main` is not a sync target. Maintainers
must enable the included workflow on their repository's default branch before
scheduled checks can run.

## Bot policy

`app/Http/Middleware/CauchonBotPolicy.php` holds the fork policy:

- `PUBLIC_ROBOT_EXCEPTIONS` removes selected search, user-requested fetch, and
  link-preview, reader, citation, preservation, and diagnostic tokens from the effective block list.
- `TRAINING_ROBOTS` keeps named model-training and data-collection agents
  blocked even if upstream changes its list.

Upstream's `BadBotBlocker::BAD_ROBOTS` array remains intact. Both the HTTP
middleware and generated `robots.txt` use the effective list from
`CauchonBotPolicy`. Six generic substring entries are retired separately; the old
Discord-specific `dbot` workaround is no longer needed. The effective HTTP list
contains 1,308 entries at this baseline. The mixed Safari/Facebook/Facebot/Twitter
preview signature bypasses only the inappropriate Facebook-network check and is
always marked as a robot for route restrictions. It receives no verified identity
or private access. Other identity checks and site privacy settings still apply.

For first visits, browser-looking clients
receive the requested response and a test cookie together, without discarding
application cookies. The NotFound handler also redirects bare homepage GETs for
robots while retaining 404 responses for unknown routes. Generated robots guidance covers query-routed
URLs as well as clean URLs, and adds the robots-only `Applebot-Extended` opt-out.

At 2.2.6, the robots handler is `app/Http/RequestHandlers/RobotsTxt.php` and the
policy test is `tests/app/Http/Middleware/CauchonBotPolicyTest.php`. Later releases
may move these to `app/Http/Controllers/` and `tests/Unit/` respectively; review
both paths during upgrades.

## Stable sync automation

From a clean, up-to-date local `main` with an `upstream` remote pointing to
`https://github.com/fisharebest/webtrees.git`, run:

```sh
./scripts/prepare-upstream-sync.sh
```

The script requires Git, GitHub CLI authentication, Python 3, and PHP. It selects
the highest numeric stable version among published upstream GitHub releases,
excluding drafts and prereleases. It merges only that release tag, never
`upstream/main`, and refuses downgrades and divergent stable history.
Reserve plain numeric and `v`-prefixed numeric tags for upstream releases; use a
fork-specific suffix such as `2.2.6-fork.1` when tagging a fork release.

The stable-sync workflow runs daily at 09:17 UTC or by manual dispatch. It prepares a deterministic
`sync/upstream-<version>` branch and bot-list report in a read-only job. A separate
job publishes the draft PR. Existing open review PRs are left for human review;
closed reviews require human intervention. Conflicts fail the run without
publishing an unresolved merge. Monitor failed workflow runs as well as PRs.

`--prepare-only` produces the report, PR body, and Git bundle without publishing;
`SYNC_OUTPUT_DIR` chooses the output directory. Run it in a disposable clean
checkout because preparation creates and switches branches.

The schedule becomes active only after the workflow reaches the default branch
and Actions is enabled for the fork. GitHub Actions must be permitted to create
pull requests in repository settings. A PR opened with `GITHUB_TOKEN` does not
trigger the normal PR workflows automatically; complete the validation below
before merging. The workflow does not install Composer dependencies or claim a
full test-suite pass.

The offline sync regression tests use temporary local Git repositories and mock
GitHub and PHP commands; they do not contact GitHub or validate PHP behavior:

```sh
python3 -m unittest discover -s tests/sync -v
```

## Review and deployment

Every upstream update needs an owner-reviewed draft PR. Review each added blocked
token using its operator's current documentation, classify its purpose, and record
whether to retain it, add a public exception, or handle it separately. Inspect
changes to bot identity checks and both policy call sites, even when the block
list is unchanged. Resolve conflicts deliberately and verify the middleware and
robots output stay aligned.

Run the policy test with Composer development dependencies installed:

```sh
vendor/bin/phpunit tests/app/Http/Middleware/CauchonBotPolicyTest.php
```

A syntax check or dependency-free policy smoke check does not replace PHPUnit or
an integration check of generated robots output. Owner signoff may be an explicit
PR comment and merge when the owner cannot formally approve their own PR.
Automation must never merge, deploy, or tag a fork release.

Publishing a fork release and deploying it are separate steps; merging an
upstream update does not update an installation. Keep deployment inventories,
server addresses, logs, credentials, and rollback records outside the public
source repository. See [robots.txt deployment](docs/robots-deployment.md) for
site-independent installation guidance.
