# Deploy on merge (WordPress-native pages)

GitHub Pages deploys the iframed pages the moment `main` moves. The pages served
natively from WordPress do not deploy that way: their content is written into the
post by `scripts/wp_publish_post.py`. Until September 2026 that was a hand step, and
PR #909 sat fixed-but-unpublished for weeks because nobody ran it.

`scripts/wp_deploy_on_merge.py` closes that for the rows in `wp/deploy.tsv`.

## What runs

A cron line in rt-ai's `config/crontab.expected` (installed by its deploy leg) runs
the script every 10 minutes as `tschultz` on rt-ai-02, against a dedicated clone at
`/srv/ai-data/wp-deploy/rt-wordpress-content`. Nobody edits in that clone.

Each run:

1. Refuses unless the clone is on `main` with a clean tree.
2. Fetches `origin/main` and fast-forwards.
3. Diffs `<state>/deployed.sha..origin/main` and maps the changed paths onto
   `wp/deploy.tsv` rows via their `wp/pages.tsv` source column. A change under
   `scripts/lib/`, or to `scripts/wp_publish_post.py` or either manifest, republishes
   every row (the converter decides what gets written).
4. Runs `scripts/wp_publish_post.py publish --target production <slug>` per row, in
   manifest order, stopping at the first failure. That script owns every safety
   guard: identity check, backup to `~/wp-post-backups/`, pattern-ref drop refusal,
   read-back compare, `post_status` guard. It is a no-op when the post already
   matches, so a re-run is safe.
5. Records a GitHub Deployment (environment `production`) with a success or
   failure status on the merged sha. If the Deployments API refuses, it falls back
   to a commit comment. GitHub failures never change what was published.
6. Appends one JSON line to `<state>/deploy.jsonl` and, on success, advances
   `<state>/deployed.sha`.

State dir: `/srv/ai-data/wp-deploy`.

## When it stops

- **A failed publish** writes `<state>/deploy-hold.json` (with the publisher's
  output tail) and the leg does nothing until a person removes that file. The
  deployed sha is not advanced, so clearing the hold re-runs the same range.
- **`<state>/hooks-off`** is the kill switch.
- **First run** on an empty state records the current sha as the baseline and
  publishes nothing, so turning the leg on never bulk-publishes history.

## Which rows

Only rows whose merge should *be* the release belong in `wp/deploy.tsv`. Kept out
on purpose: the three release drafts (the rail cannot publish a draft anyway) and
the two rows that carry staging post ids. Add a row when a page is live on
production and its source in this repo is the source of record.

## Checking

```bash
python3 scripts/wp_deploy_on_merge.py --dry-run          # what the next run would do
tail -3 /srv/ai-data/wp-deploy/deploy.jsonl              # what the last runs did
tail -20 /srv/ai-data/deploy/wp-deploy.log               # cron output
```
