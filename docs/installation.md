# Installing and releasing webtrees-less-restrictive

This is an independent fork of webtrees. The source repository is currently
[Cauchon/webtrees](https://github.com/Cauchon/webtrees); the product name is
**webtrees-less-restrictive**. A reviewed source commit, a published fork release,
and a deployed installation are three separate states.

## Requirements

The web server needs PHP 8.3 through 8.6 and the extensions required in
[`composer.json`](../composer.json). Install the PDO driver for the chosen
database. MySQL is recommended; PostgreSQL, SQL Server, and SQLite are also
supported. Provide adequate disk space, PHP memory, and execution time for the
application, dependencies, database, GEDCOM files, and media. Keep the site's
`data/` directory writable by PHP and protected from public access. Configure
URL rewriting if using pretty URLs.

## New installation

1. Choose a published release in the [fork's Releases page](https://github.com/Cauchon/webtrees/releases)
   and download its attached `webtrees-<fork-tag>.zip` distribution. Check the
   tag and recorded SHA-256 digest against the release notes. GitHub's generated
   **Source code** ZIP lacks the bundled PHP dependencies; an upstream webtrees
   ZIP lacks this fork's policy.
2. Extract the distribution. Upload the **contents** of its `webtrees/` folder
   to an empty site directory, preserving the directory structure. Arrange the
   web server and writable `data/` directory according to your hosting setup.
3. Open the site URL and complete the webtrees setup wizard. Create a tree or
   import a GEDCOM file, then review privacy settings.
4. Inspect the generated robots response and any domain-root static copy using
   [the robots deployment guide](robots-deployment.md). Verify both public
   reading and private access rules on the actual installation.

If no fork release is published yet, there is no public fork distribution to
install. A maintainer may build one from a reviewed source commit as described
below; do not substitute an upstream package.

## Manual upgrade

The in-app automatic upgrade wizard contacts the upstream webtrees update
service and downloads its package. **Do not run it on this fork:** it can
overwrite the fork's code and bot policy. An upstream sync PR or merge does not
change a running installation.

1. Obtain the newer **fork distribution** ZIP and review its release notes and
   requirements. Make a restorable backup of the database and the entire
   `data/` directory, including configuration and media. Keep a copy of the
   currently deployed application files for rollback.
2. Put the site into maintenance mode by creating `data/offline.txt`. Confirm
   that public requests show the maintenance page.
3. Extract the ZIP and **merge** the contents of `webtrees/` into the existing
   site directory. Preserve the existing `data/` directory and any local
   configuration or modules. Use a file transfer method that merges directories;
   replacing the site directory can erase data. Avoid serving a mix of old and
   new application files while the copy runs.
4. Verify PHP compatibility, database connectivity, login, a representative
   public tree page, and the generated `robots.txt` output. Update any
   domain-root static robots copy and clear relevant caches if the policy or
   tree names changed.
5. Remove `data/offline.txt` only after verification. If verification fails,
   restore the previous application files and matching database/`data/` backup
   under maintenance mode, then investigate before retrying.

Keep server addresses, secrets, backup locations, and deployment logs in a
private operations record outside this source repository.

## Building and publishing a fork release

A maintainer can build a candidate artifact from a reviewed source commit for
validation. Publishing and deploying remain separate owner actions after
release approval.

1. Use a disposable clean checkout of the reviewed release commit. If source
   JavaScript or CSS changed, run `npm ci` and `npm run production` first, then
   review and commit the generated assets. Node/npm is only needed when
   rebuilding those assets.
2. Install build tools: Git, tar, zip, Composer, PHP 8.3 through 8.6, Python 3
   for the robots tests, and the extensions required by `composer.json`. Run
   `composer validate` and `composer install`. Compile translations with
   `php index.php compile-po-files`, then run
   `php -d memory_limit=1G vendor/bin/phpunit` and
   `python3 -m unittest discover -s tests/robots -v`. On the final clean
   commit, create a local fork-specific annotated tag such as
   `2.2.6-fork.1`. Reserve plain numeric and `v`-prefixed numeric tags for
   upstream releases. The tag identifies exactly the commit to package;
   `composer webtrees:build` archives `HEAD` and names the ZIP using
   `git describe`.
3. Run `composer webtrees:build`. It archives the committed source, installs
   production PHP dependencies into the distribution, compiles translations,
   and creates `webtrees-<git-describe>.zip`. Check that the output name uses
   the intended fork tag; test the ZIP and inspect it for `webtrees/vendor/`,
   application files, and compiled translations. Record its SHA-256 digest. The
   build runs `composer install --no-dev` in the checkout, which is why a
   disposable checkout is recommended.
4. Test a manual install or upgrade from the candidate ZIP. After final owner
   signoff, attach that same ZIP to a release in this fork's repository with
   accurate release notes and its SHA-256 digest. Do not present GitHub's
   generated source ZIP as installable.

The [stable sync workflow](../.github/workflows/stable-upstream-sync.yaml)
prepares a draft review PR only. It does not build, tag, publish, or deploy a
fork release. Scheduled checks require the workflow on the repository's default
branch, Actions enabled, and permission for Actions to create pull requests.
PRs created with `GITHUB_TOKEN` do not automatically run the normal PR
workflows, so maintainers must complete validation before merge.
