# Schema Control Manager

A WordPress plugin for managing custom JSON-LD schemas per page target, with fine-grained control over coexistence with AIOSEO.

## What it does

Schema Control Manager lets you define **rules** that map a page target (a post type, a URL, a category, an author page…) to one or more **custom schemas** written in JSON-LD. For each rule you choose how the custom schema interacts with the graph that AIOSEO produces for the same page.

## Supported rule targets

| Target type | Matches |
|---|---|
| `home` | Front page or blog index |
| `front_page` | Static front page only |
| `exact_url` | Absolute URL or relative path |
| `exact_slug` | Post slug (any post type) |
| `post_type` | All singular posts of a given type |
| `post_type_archive` | CPT archive page |
| `category` | Category archive |
| `tag` | Tag archive |
| `taxonomy_term` | Any taxonomy term archive (`taxonomy:term-slug`) |
| `author` | Author archive page |

Rules are sorted by **priority** (higher = evaluated first) then by specificity, so more specific targets win naturally.

## Supported modes

| Mode | Behaviour |
|---|---|
| `aioseo_only` | AIOSEO output unchanged; custom schemas inactive |
| `aioseo_plus_custom` | Custom nodes merged additively into the AIOSEO graph |
| `custom_override_selected` | Selected AIOSEO structural types replaced by custom nodes; @id references rewired |
| `custom_only` | AIOSEO output suppressed; only custom schemas are injected |

## Dynamic placeholders

Schemas can include `{{token}}` placeholders resolved at render time:

| Token | Value |
|---|---|
| `{{post_title}}` | Post title |
| `{{post_url}}` | Canonical post URL |
| `{{post_excerpt}}` | Post excerpt |
| `{{post_id}}` | Post ID |
| `{{post_type}}` | Post type slug |
| `{{post_date}}` | Post publish date |
| `{{post_modified_date}}` | Post last modified date |
| `{{featured_image_url}}` | Featured image URL |
| `{{featured_image_alt}}` | Featured image alt text |
| `{{author_name}}` | Post author display name |
| `{{author_slug}}` | Post author nicename |
| `{{author_email}}` | Post author email |
| `{{site_name}}` | Site name |
| `{{site_url}}` | Site URL |
| `{{queried_term_name}}` | Current term name (archive pages) |
| `{{queried_term_slug}}` | Current term slug |
| `{{queried_taxonomy}}` | Current taxonomy name |
| `{{term:TAXONOMY}}` | Name of the primary term in TAXONOMY for the current post |
| `{{meta:FIELD_KEY}}` | Post meta value for FIELD_KEY |
| `{{archive_post_type}}` | Post type slug on archive pages |
| `{{archive_post_type_label}}` | Post type label on archive pages |
| `{{archive_post_type_url}}` | Post type archive URL |

Unresolved tokens are replaced with an empty string.

## Simulated preview

The rule editor includes a **Simulated Preview** tool that lets you select any published post and render the resolved schema graph for that post under the rule's configuration — without visiting the frontend. Posts are pre-filtered to match the rule context (post type, category, taxonomy term, etc.).

## JSON cache

On every rule or schema create / update / delete, the plugin writes a snapshot of all active rules (with their active schemas) to:

```
wp-content/uploads/scm-cache/scm-rules-cache.json
```

The database remains the authoritative source of truth. The cache file is intended for auditing, debugging, or external tooling. The directory is protected from direct HTTP access via `.htaccess` and `index.php`.

## AIOSEO integration

This plugin is designed primarily as a companion to **All in One SEO (AIOSEO)**. It hooks into the AIOSEO JSON-LD output at the `wp_head` level, merges or replaces nodes as configured, and re-injects the final graph. When AIOSEO is not active, rules that use custom nodes are still injected normally.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- All in One SEO (optional, for AIOSEO-aware modes)
