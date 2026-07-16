---
name: pro-free-release-sync
description: Pre-release / pre-merge checklist keeping the free plugin (boardscribe) and Pro (boardscribe-pro) repos compatible. Use before tagging a release of either plugin, before merging a lockstep feature, or when asked to verify free/Pro compatibility.
---

# Free ↔ Pro release-sync checklist

The two plugins live in **separate repos** (local checkout `boardscribe` free, sibling `../boardscribe-pro`; GitHub repos `equalizedigital/boardscribe`/`equalizedigital/boardscribe-pro`, renamed from the historical meeting-minutes names) developed in lockstep with **matching branch names** for paired features. Nothing compiles across the boundary — these manual checks are the only guard.

## 1. Hook-contract check (every Pro release)

- Read free's `docs/HOOK-CONTRACT-CHANGES.md`. For each entry, grep Pro for the hook name and verify each callback matches the current signature.
- Standing caveat: `edbs_meeting_row_data`'s 3rd arg `$request` may be `null` (rows built via `BoardScribeEndpoint::build_meeting_row()` outside REST). Pro callbacks must type-hint `?\WP_REST_Request`, or leave untyped and null-check.
- Reverse check: `grep -rhoP "edbs_[a-z_]+" <pro>/includes <pro>/src | sort -u` and confirm every consumed hook still exists in free (`grep -rn "<hook>" <free>/includes <free>/partials <free>/src`). A hook renamed/removed in free silently breaks Pro.

## 2. Signature-change discipline (every free change)

If a free PR changes an **existing** hook's call signature (args, types, nullability — not new hooks), it MUST add an entry to free's `docs/HOOK-CONTRACT-CHANGES.md` before merge. New hooks go in free's CLAUDE.md extension-point table instead.

## 3. Lockstep branch pairing

- For a feature spanning both repos: same branch name in both, paired PRs, **merge free first** (Pro may consume what free adds; never the reverse ordering).
- Before merging either PR, check the paired repo's branch/PR state: `git -C <other-repo> branch -a | grep <branch>` and `gh pr list --repo equalizedigital/<other-repo>`.

## 4. JS registry contracts

Pro's frontend hangs off free's window globals — confirm they still exist in free's `src/js` after any free JS refactor:
`window.edbsExtraColumns`, `window.edbsTemplates`, `window.edbsBuildTable( meetings, instanceCfg )`, `window.edbsEscapeAttr`, `instanceCfg.resolvedTemplate`, and the `edbs-template-<name>` root-class convention.
Editor side: `window.edbsBlockFieldRegistry` (localized onto free's block editor bundle) and the `edbs.block.templateChangeAttributes` wp.hooks filter (Pro's `src/js/editor/blockEditor.js` couples postsPerPage to the year-timeline template through it).

## 4b. Block version pairing (since the block moved to free)

The `equalize-digital/boardscribe` block is registered by **free** (`Block/BoardScribeBlock.php`); Pro only extends it. New Pro requires the paired new free release (block otherwise missing, and `ProMetaFields::get_meeting_year()` calls free's public `BoardScribeEndpoint::parse_date()`). Old Pro + new free is safe: free registers at `init` 20 behind an `is_registered()` guard, so old Pro's own block registration wins.

## 5. Review gates (both repos)

CodeRabbit **and** Gemini Code Assist both auto-review every PR — wait for both to finish (not "pending"), surface findings for discussion before fixing, and reference the fixing commit hash in thread replies.

## 6. Release packaging

Version bumps: plugin header + `EDBS_VERSION` / `EDBS_PRO_VERSION` constant + readme.txt stable tag; replace any `@since x.x.x` placeholders in the release. Then package via the `package-plugins` skill (JS build is mandatory; built output is gitignored in both repos).
