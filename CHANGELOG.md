# Changelog

## 1.0.0 — 2026-04-16

### Initial production release

#### Rule engine
- Rule matching against 10 target types: `home`, `front_page`, `exact_url`, `exact_slug`, `post_type`, `post_type_archive`, `category`, `tag`, `taxonomy_term`, `author`
- Four coexistence modes: `aioseo_only`, `aioseo_plus_custom`, `custom_override_selected`, `custom_only`
- Priority + specificity sorting ensures the most relevant rule wins per request
- Cascade delete: removing a rule removes all its attached schemas

#### Dynamic placeholders
- 20+ `{{token}}` placeholders resolved at render time (post, author, taxonomy, site, meta, archive)
- Unresolved tokens silently replaced with empty string
- In-editor variable picker with search filter and group categories

#### Simulated preview
- Context-aware post filtering by rule target type
- Live search/filter above the post selector
- Match status indicator (matches / does not match / archive rule notice)
- Improved empty-graph messaging (distinguishes rule mismatch from schema configuration issues)

#### Duplicate rule
- One-click rule duplication with full schema clone
- Copy starts inactive; label suffixed with ` (Copia)`

#### Improved admin UX
- Final Graph Preview panel: collapsible error/warning sections, copy JSON, expand/collapse viewer
- Rule-level diagnostics banner (all schemas combined)
- Runtime notice display from last frontend render
- Settings page with preview language selector (English / Español)
- Admin interface fully in Spanish by default

#### JSON cache
- `SCM_Cache` class writes `scm-rules-cache.json` to `wp-content/uploads/scm-cache/`
- Regenerated automatically on every rule/schema create, update, or delete
- Cache directory protected with `.htaccess` and `index.php`
- Active rules only, with nested active schemas; DB remains source of truth

#### Graph diagnostics
- Per-schema and rule-level diagnostic analysis
- Critical errors: empty graph, duplicate `@id`, broken references, invalid `@type`
- Structural warnings: missing `@id` on structural nodes, multi-domain `@id`, mode misuse
- Semantic warnings: `ProfilePage` without `mainEntity`, `Person` on non-author target
