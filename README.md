# Codex Blog Abilities

Codex Blog Abilities is a small WordPress plugin that exposes guarded blog
administration actions through the WordPress MCP Adapter.

It is intended for a trusted MCP client, such as Codex, that authenticates with a
WordPress Application Password.

## Requirements

- WordPress 6.9 or newer
- PHP 7.4 or newer
- The WordPress MCP Adapter plugin
- A WordPress user with the capabilities required for the requested action

## Abilities

The plugin registers public MCP abilities under the `codex-blog/` namespace:

- `get-site-info`
- `list-post-types`
- `list-posts`
- `get-post`
- `create-post`
- `update-post`
- `delete-post`
- `schedule-post`
- `upload-media-from-url`
- `set-featured-image`
- `set-featured-image-from-url`
- `remove-featured-image`
- `get-seo-meta`
- `update-seo-meta`
- `audit-post-seo`
- `list-terms`
- `create-term`
- `update-term`
- `delete-term`
- `list-comments`
- `update-comment`
- `delete-comment`
- `list-media`
- `list-plugins`
- `activate-plugin`
- `deactivate-plugin`
- `get-options`
- `update-options`

## Safety Model

The plugin relies on native WordPress capabilities before executing actions.
For example, post edits require `edit_post`, plugin management requires
`activate_plugins`, and option changes require `manage_options`.

The option update ability is intentionally restricted to a small allowlist of
common site settings. It does not expose arbitrary `update_option()` access.

SEO metadata updates use All in One SEO's runtime API when it is available and
also write known post-meta fallbacks for compatibility. The SEO audit is
deterministic: it checks content and metadata structure but does not generate
ranking claims.

Deactivation of `mcp-adapter` and this plugin is blocked by default so an MCP
client does not accidentally remove its own control plane.

## Installation

Copy this directory to:

```text
wp-content/plugins/codex-blog-abilities
```

Then activate it:

```bash
wp plugin activate codex-blog-abilities
```

## Development

Run a PHP syntax check before deploying:

```bash
php -l codex-blog-abilities.php
```

## Releases

Push a semantic version tag to build an installable ZIP through GitHub Actions:

```bash
git tag vX.Y.Z
git push origin main vX.Y.Z
```

The release artifact is named `codex-blog-abilities-<tag>.zip`.

## License

GPL-2.0-or-later.
