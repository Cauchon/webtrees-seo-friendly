#!/usr/bin/env bash
# Runs only from a trusted main checkout, after the read-only preparation job.
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo 'Usage: scripts/publish-upstream-sync.sh ARTIFACT_DIRECTORY' >&2
    exit 2
fi
artifact=$1
[[ $(git branch --show-current) == main ]] || { echo 'Publish from main only.' >&2; exit 1; }
[[ -z $(git status --porcelain) ]] || { echo 'Main checkout must be clean.' >&2; exit 1; }
[[ $(cat "$artifact/status") == prepared ]] || { echo 'Artifact is not a prepared sync.' >&2; exit 1; }
tag=$(cat "$artifact/tag")
[[ $tag =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Invalid release tag in artifact.' >&2; exit 1; }
branch="sync/upstream-${tag#v}"
[[ $(cat "$artifact/branch") == "$branch" ]] || { echo 'Branch does not match release tag.' >&2; exit 1; }
[[ -s $artifact/pr-body.md && -s $artifact/sync.bundle ]] || { echo 'Missing PR body or git bundle.' >&2; exit 1; }

repo=$(gh repo view "$(git remote get-url origin)" --json nameWithOwner --jq .nameWithOwner)
[[ $repo =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || { echo "Cannot determine GitHub fork from origin." >&2; exit 1; }

existing=$(gh pr list --repo "$repo" --state all --head "$branch" --base main --json number,state --jq '.[0].state // empty')
if [[ $existing == OPEN ]]; then
    echo "An open PR for $branch already exists."
    exit 0
elif [[ -n $existing ]]; then
    echo "A $existing PR already exists for $branch; resolve it before retrying." >&2
    exit 1
fi
remote_sha=$(git ls-remote origin "refs/heads/$branch" | cut -f1)

git bundle verify "$artifact/sync.bundle"
git fetch "$artifact/sync.bundle" "refs/heads/$branch:refs/remotes/upstream-sync/prepared"
prepared=$(git rev-parse refs/remotes/upstream-sync/prepared)
base=$(git rev-parse main)
if ! git merge-base --is-ancestor "$base" "$prepared"; then
    echo 'Prepared branch does not contain current main; refusing stale artifact.' >&2
    exit 1
fi

if [[ -n $remote_sha && $remote_sha != "$prepared" ]]; then
    echo "Remote branch $branch differs from prepared commit; refusing to overwrite it." >&2
    exit 1
fi
if [[ -z $remote_sha ]]; then git push origin "$prepared:refs/heads/$branch"; fi
gh pr create --repo "$repo" --draft --base main --head "$branch" \
    --title "Review webtrees stable $tag sync" --body-file "$artifact/pr-body.md"
echo "Published draft PR for $branch"
