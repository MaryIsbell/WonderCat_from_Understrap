---
name: wikidata-doc-sync
description: 'Comprehensively sync WonderCat Wikidata docs with current code. Use when updating inc/wikidata/docs, validating DATA-FLOW.md or TEMPLATE-TAGS.md accuracy, documenting hooks/cache/guards, or preparing developer-facing architecture references after code changes.'
argument-hint: 'What changed in Wikidata logic, and which docs should be synchronized?'
user-invocable: true
---

# Wikidata Documentation Sync

## Outcome
Produce accurate, developer-facing documentation in inc/wikidata/docs that reflects the current WordPress theme behavior, including:
- Runtime data flow from input to storage to rendering
- Validation, cache, and background refresh behavior
- Public template-tag API signatures and behavior
- Known guards, limitations, and operational assumptions

## When To Use
Use this skill when:
- Wikidata PHP logic changed in inc/wikidata.php or inc/wikidata/.
- Template tags changed in inc/wikidata/template-tags.php.
- Caching/refresh constants or cron hooks changed in inc/wikidata/utilities.php.
- Docs in inc/wikidata/docs appear stale, incomplete, or contradictory.
- You are preparing handoff docs for future maintainers.

## Inputs
- Primary target docs: inc/wikidata/docs/DATA-FLOW.md and inc/wikidata/docs/TEMPLATE-TAGS.md
- Source code: inc/wikidata.php, inc/wikidata/utilities.php, inc/wikidata/table.php, inc/wikidata/rewrite.php, inc/wikidata/template-tags.php, wikidata-entity.php, functions.php
- Context from AGENTS.md and any recent change history

## Documentation Standards
- Use narrative code references (file/function-level context) rather than mandatory line-level citations for every claim.
- When behavior is uncertain, conflicting, or environment-dependent, include an explicit "Maintainer Note" callout in docs.
- Keep maintainer notes actionable: explain uncertainty source and what to verify.

## Procedure
1. Establish scope and audience.
- Confirm whether this is a full doc sync or focused sync (for example, validation-only, template-tags-only).
- Write for future users and developers: concise architecture narrative, accurate behavior, minimal assumptions.

2. Build code truth map before editing docs.
- Enumerate entry points, hooks, constants, and storage schema.
- Capture major flows: validate, fetch, cache, upsert, render, refresh.
- Record guard conditions and fail modes.

3. Compare docs against code and identify drift.
- For each significant behavior in docs, verify implementation exists.
- For each significant implementation path in code, verify docs mention it.
- Mark mismatches as one of:
  - Missing in docs
  - Outdated in docs
  - Ambiguous in code (needs caveat)

4. Update DATA-FLOW.md first.
- Ensure it documents lifecycle, guard rails, cache tiers, cron refresh paths, and fetch points.
- Keep examples and tables aligned with actual constants/hook names.
- Explain operational consequences (for example, what happens when validation cannot verify).

5. Update TEMPLATE-TAGS.md second.
- Ensure documented functions, parameters, return types, and examples match current template-tags implementation.
- Remove or flag deprecated or non-existent tags.
- Include behavior notes for language fallback, empty values, escaping, and performance-sensitive usage.

6. Apply verification gates.
- Gate A: Every hook/function/constant named in docs is present in code.
- Gate B: Every critical runtime branch (validation success/failure, cache hit/miss, refresh scheduling) is represented in docs.
- Gate C: No contradictions between DATA-FLOW.md and TEMPLATE-TAGS.md.
- Gate D: Documentation avoids speculative behavior.

7. Perform quality pass.
- Keep wording precise and maintainable.
- Prefer short sections, tables, and flow diagrams where useful.
- Preserve stable terminology across both docs (QID, entity row, post linkage, refresh).

8. Finalize and report.
- Summarize what was synchronized.
- List residual risks, unknowns, or runtime assumptions that still require manual verification.
- Propose follow-up doc tasks if needed.

## Branching Logic
- If code behavior is clear and deterministic: document as authoritative behavior.
- If behavior depends on environment/plugins/config and cannot be proven statically: document as conditional behavior and state prerequisites.
- If docs and code conflict and intent is unclear: document observed code behavior and add a maintainer note calling out uncertainty.
- If a function is public but not used internally: still document API contract if it is part of theme-facing surface.

## Completion Criteria
- DATA-FLOW.md and TEMPLATE-TAGS.md are both updated and internally consistent.
- All major Wikidata flows and guards are traceable to code.
- No stale hook names, constants, or function references remain.
- Output is understandable to future maintainers without requiring tribal context.

## Suggested Prompts
- /wikidata-doc-sync Full sync after recent QID validation hardening; refresh both docs.
- /wikidata-doc-sync Sync TEMPLATE-TAGS.md only against current template-tags.php signatures.
- /wikidata-doc-sync Audit cache and refresh behavior docs for utilities.php and rewrite DATA-FLOW.md sections.
