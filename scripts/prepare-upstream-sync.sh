#!/usr/bin/env bash
set -euo pipefail

root=$(git rev-parse --show-toplevel)
cd "$root"

if [[ ${1:-} != '' && ${1:-} != '--prepare-only' ]]; then
    echo 'Usage: scripts/prepare-upstream-sync.sh [--prepare-only]' >&2
    exit 2
fi
prepare_only=false
[[ ${1:-} == '--prepare-only' ]] && prepare_only=true

if [[ $(git branch --show-current) != main ]]; then
    echo 'Start an upstream sync from the local main branch.' >&2
    exit 1
fi
if [[ -n $(git status --porcelain) ]]; then
    echo 'Commit or stash local changes before starting an upstream sync.' >&2
    exit 1
fi

repo=$(gh repo view "$(git remote get-url origin)" --json nameWithOwner --jq .nameWithOwner)
[[ $repo =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || { echo "Cannot determine GitHub fork from origin." >&2; exit 1; }

output_dir=${SYNC_OUTPUT_DIR:-$(mktemp -d "${TMPDIR:-/tmp}/webtrees-sync.XXXXXX")}
mkdir -p "$output_dir"
output_dir=$(cd "$output_dir" && pwd)

# Query every release page; GitHub omits draft releases from unauthenticated reads,
# but filtering them explicitly also handles authenticated responses.
releases=$(gh api --paginate -X GET 'repos/fisharebest/webtrees/releases?per_page=100' \
    --jq '.[] | select(.draft == false and .prerelease == false) | .tag_name')
tag=$(printf '%s\n' "$releases" | python3 scripts/select-stable-release.py)
version=${tag#v}
branch="sync/upstream-$version"
printf '%s\n' "$tag" > "$output_dir/tag"
printf '%s\n' "$branch" > "$output_dir/branch"

# A published release tag, rather than upstream/main, is the only merge input.
git fetch upstream "refs/tags/$tag:refs/remotes/upstream/stable-sync-release"
release=refs/remotes/upstream/stable-sync-release

# The highest stable version already incorporated by main is our baseline. A
# lower or equal release is never merged, even if its tag points to a new SHA.
current_tags=$(git tag --merged main | python3 scripts/select-stable-release.py 2>/dev/null || true)
if [[ -n $current_tags ]]; then
    current_version=${current_tags#v}
    comparison=$(python3 - "$current_version" "$version" <<'PY'
import sys
left, right = ([int(part) for part in v.split('.')] for v in sys.argv[1:])
print((left > right) - (left < right))
PY
)
    if [[ $comparison -ge 0 ]]; then
        echo "No update: main already includes stable $current_tags; newest published stable is $tag."
        printf 'no-update\n' > "$output_dir/status"
        exit 0
    fi
    if ! git merge-base --is-ancestor "$current_tags" "$release"; then
        echo "Release $tag does not descend from incorporated stable $current_tags; refusing divergent release history." >&2
        exit 1
    fi
fi
if git merge-base --is-ancestor "$release" main; then
    echo "No update: main already contains stable $tag."
    printf 'no-update\n' > "$output_dir/status"
    exit 0
fi

# Existing open PRs are left for review. Closed PRs are not silently recreated.
existing=$(gh pr list --repo "$repo" --state all --head "$branch" --base main --json number,state --jq '.[0].state // empty')
if [[ $existing == OPEN ]]; then
    echo "An open PR for $branch already exists."
    printf 'existing-pr\n' > "$output_dir/status"
    exit 0
elif [[ -n $existing ]]; then
    echo "A $existing PR already exists for $branch; resolve it before retrying." >&2
    exit 1
fi
remote_sha=$(git ls-remote origin "refs/heads/$branch" | cut -f1)
if [[ -n $remote_sha ]]; then
    git fetch origin "refs/heads/$branch:refs/remotes/origin/stable-sync-existing"
    if ! git merge-base --is-ancestor main "$remote_sha" || ! git merge-base --is-ancestor "$release" "$remote_sha"; then
        echo "Remote branch $branch exists but does not contain main and stable $tag; refusing to replace it." >&2
        exit 1
    fi
fi

base=$(git merge-base main "$release")
python3 scripts/review-upstream-bots.py --base "$base" --head "$release" > "$output_dir/bot-review.md"
if [[ -n $remote_sha ]]; then
    git switch -c "$branch" "$remote_sha"
else
    git switch -c "$branch"
fi
if ! git merge --no-edit "$release"; then
    echo "Merge conflicts on $branch. Resolve locally; no branch or PR was published. Bot review: $output_dir/bot-review.md" >&2
    exit 1
fi

if ! grep -Fq 'CauchonBotPolicy::isBlocked(' app/Http/Middleware/BadBotBlocker.php; then
    echo 'The bot middleware no longer calls CauchonBotPolicy::isBlocked.' >&2
    exit 1
fi
for source in app/Http/Middleware/CauchonBotPolicy.php app/Http/Middleware/BadBotBlocker.php; do
    php -l "$source"
done
if [[ -f app/Http/RequestHandlers/RobotsTxt.php ]]; then
    robots_file=app/Http/RequestHandlers/RobotsTxt.php
elif [[ -f app/Http/Controllers/RobotsTxt.php ]]; then
    robots_file=app/Http/Controllers/RobotsTxt.php
else
    echo 'Cannot find RobotsTxt in RequestHandlers or Controllers.' >&2
    exit 1
fi
if ! grep -Fq 'CauchonBotPolicy::blockedRobots(' "$robots_file"; then
    echo "$robots_file no longer calls CauchonBotPolicy::blockedRobots." >&2
    exit 1
fi
php -l "$robots_file"
policy_test=''
for candidate in tests/app/Http/Middleware/CauchonBotPolicyTest.php tests/Unit/Http/Middleware/CauchonBotPolicyTest.php; do
    if [[ -f $candidate ]]; then policy_test=$candidate; break; fi
done
if [[ -z $policy_test ]]; then
    echo 'Cannot find CauchonBotPolicyTest in the known test paths.' >&2
    exit 1
fi
if [[ -x vendor/bin/phpunit ]]; then
    vendor/bin/phpunit "$policy_test"
    test_result='Policy PHPUnit test passed.'
else
    test_result='PHPUnit dependencies were absent; run the policy test before merging.'
    echo "$test_result" >&2
fi

{
    printf 'This draft PR merges published stable webtrees release `%s` into webtrees-seo-friendly. It must remain unmerged until the site owner and Codex review every new blocked token and record decisions below.\n\n' "$tag"
    cat "$output_dir/bot-review.md"
    printf '\n## Validation\n\nPHP syntax checks passed. %s Review the branch diff before merging.\n' "$test_result"
} > "$output_dir/pr-body.md"
printf 'prepared\n' > "$output_dir/status"

if [[ $prepare_only == true ]]; then
    git bundle create "$output_dir/sync.bundle" "refs/heads/$branch" ^main
    echo "Prepared $branch for stable $tag in $output_dir"
else
    git push -u origin "$branch"
    gh pr create --repo "$repo" --draft --base main --head "$branch" \
        --title "Review webtrees stable $tag sync" --body-file "$output_dir/pr-body.md"
    echo "Published draft PR for $branch. Bot review: $output_dir/bot-review.md"
fi
