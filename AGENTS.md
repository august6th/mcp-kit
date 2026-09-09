# mcp-kit Codex Rules

These rules mirror `CLAUDE.md` for Codex work in this directory.

## PHP compatibility

- At the start of each execution, print `当前模型：<model name>` to match the ERP model-name-prefix rule.
- After a destructive or large change, include a concise file-responsibility review following the ERP post-large-change-review structure.
- This is a Laravel 5.5 / PHP 7.0-compatible Composer package.
- Do not use `declare(strict_types=1)`, typed properties, typed parameters, return types, or PHP 7.1+ syntax.
- Use constructor injection and keep the package PSR-4 isolated from the OMS namespace.
- Preserve existing Laravel repository/model conventions and make the smallest compatible change.
- Follow existing ERP PHP constraints: respect real index prefixes, avoid unnecessary casts and N+1 queries, use BCMath for money, and process growing tables through bounded cursor/batch operations.
- Models use the existing `$guarded = []` convention rather than introducing `$fillable`.
- Before adding `whereIn` or large-range queries, verify real indexes, set deterministic limits, and use `EXPLAIN` to rule out full-table scans.
- New DDL uses `NOT NULL` plus explicit defaults where valid, and composite-index selectivity should decrease from left to right.
- Keep mcp-kit installed as the OMS Composer path package/symlink. Do not wire live Kernel aliases or routes until `composer update erp/mcp-kit --ignore-platform-reqs` has installed it successfully.

## Tool contract

- `schema()` is the single source for MCP discovery, portal catalog, `mcp:docs`, and gateway synchronization.
- The schema is the source of truth: change the tool schema first, then run synchronization; do not add migrations for business-tool metadata.
- Every public tool schema MUST define model-facing `description` plus portal-facing `business_label` and `business_description`.
- Every public parameter MUST define `business_label` and `business_description` without weakening its model-facing `description`.
- Enum parameters MUST define `business_values` whose keys exactly match `values`.
- Every upstream MUST configure `MCP_GATEWAY_UPSTREAM_LABEL`; synchronization fails closed when any required business metadata is missing.
- Business metadata is portal-only and MUST NOT replace, shorten, or enter MCP model descriptions.
- `FieldWhitelist` is default-deny. Every new tool must explicitly define output fields and PII behavior.
- Every detail/list tool must define bounded output, pagination or aggregation, time-window limits, and an honest oversized-result response.
- Prefer aggregate tools over returning detail rows. Detail tools have a hard page-size limit; true bulk work goes through ERP export jobs rather than an LLM response.
- State and enum parameter descriptions must list every value and match the source code and database meaning.
- `mcp:sync-gateway` must send only public tool metadata; inject policies and authorization logic belong to the gateway.
- The sync payload must not contain secrets, scripts, SQL, or executable authorization logic.
- Gateway synchronization is fail-closed: reject any invalid tool/schema/path, never send a partial authoritative manifest, and require explicit `--allow-empty` before removing the final tool.

## Security

- `McpSignatureCheck` remains isolated from the existing `InnerSignatureCheck` implementation.
- Never return or log secrets, tokens, HMAC keys, API key plaintext, or buyer PII.
- Validate signed identity fields and keep account/brand scope enforcement deterministic.
- Keep source-side filtering and masking in addition to gateway-side output masking.
- Treat the gateway as the complete audit sink; source-side default audit implementations may remain no-op.

## Verification

- Keep code compatible with the actual OMS PHP runtime.
- Run `php -l` on changed PHP files and add focused tests for permission resolution, whitelisting, nested output, signing, schema translation, and gateway synchronization.
- For large changes, finish with a concise file-responsibility review and document any environment-limited verification.
