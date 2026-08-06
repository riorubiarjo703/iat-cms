# Code Snippets

An admin screen for injecting scripts, styles and meta tags into the public
site's `<head>` or `<body>`, replacing `CodeSnippetsPlaceholder`.

This is the first of three specs. The reference screenshots also cover Roles,
Permissions and a richer Users page; those depend on an authorization subsystem
this application does not have, and are specified separately. See *Scope* below.

## Source

Layout and field structure come from the Unfold CMS demo:

- `https://demo.unfoldcms.com/admin/code-snippets`

Unfold is a different product with its own admin shell. **Its screen structure
and field semantics are adopted; its visual style is not** — this panel is
Filament 5 with the `scbd-*` theme defined in
`resources/css/filament/admin/theme.css`, and the snippet screens use stock
Filament components so they match the rest of this admin rather than the
reference.

### What the reference gives, and what is dropped

| Reference element | Decision |
|---|---|
| Name, Type, Position, Priority fields | Kept, with the reference's helper text |
| Code + Description fields | Kept |
| Active toggle | Kept |
| "Don't load for staff/admins" toggle | Kept, as `skip_for_admins` |
| Empty state (`<>`, two buttons) | Kept |
| Help / Template / Add Snippet header actions | Kept |
| Use Template modal, six templates | Kept, with one behavioural change (see *Templates*) |
| **Zones** (All Pages / Public / Auth) | **Dropped** — see below |

**Zones are dropped.** The reference's Auth zone targets login, register and
password-reset pages. This application has no front-end authentication: its only
auth screens belong to the Filament panel at `/superduper/login`, and injecting
third-party tracking into an admin login screen is not a behaviour worth
building a control for. With Auth gone, the three-checkbox group collapses to a
single value — a control that cannot be set wrongly, so it earns no space in the
form. Every snippet applies to all public pages.

The concept returns intact if front-end auth is ever built: it becomes a
`zones` column and a checkbox group, with no change to anything specified here.

## Scope

Only Code Snippets. The other three reference screens are deliberately excluded:

| Screen | Why not here |
|---|---|
| Roles | No authorization system exists — no `spatie/laravel-permission`, no roles or permissions tables, no role column on `users`. `User::canAccessPanel()` returns `true` for every authenticated user. Building it is its own project. |
| Permissions | Same. |
| Users (stat cards, Status, Roles columns) | The `users` table has no status or role concept, and the Roles column depends on the subsystem above. |

Code Snippets depends on none of that, which is why it goes first.

## Security posture

This feature stores operator-supplied markup and emits it into public pages
**unescaped**. That is the entire point of the feature, not an oversight, and
the renderer says so at the point of output so it is not later "fixed" into
`{{ }}` — which would silently turn every snippet into visible text on the page.

The trust boundary is panel access. The panel has no self-registration
(`AdminPanelProvider` calls `->login()` and not `->registration()`), so accounts
exist only when an administrator creates one. Every account is currently a full
administrator, so anyone who can sign in can already inject site-wide
JavaScript. That is consistent with the current single-tier model, and is a
reason to build Roles next — not a reason to hold this feature back.

Nothing renders inside the Filament panel; the injection points are the public
layouts only.

## Data model

Migration `create_code_snippets_table`:

| column | type | notes |
|---|---|---|
| `id` | id | |
| `name` | string(255) | e.g. "Google Analytics" |
| `type` | string | `script` \| `style` \| `meta` \| `html` |
| `position` | string | `head` \| `body_start` \| `body_end` |
| `priority` | unsignedTinyInteger, default `10` | 0–100, lower loads first |
| `code` | text | full markup including tags |
| `description` | text, nullable | internal notes, never rendered |
| `is_active` | boolean, default `true` | |
| `skip_for_admins` | boolean, default `true` | |
| `created_at` / `updated_at` | timestamps | |

Composite index on `(is_active, position, priority)` — the exact shape of the
render query.

`priority` is stored as `unsignedTinyInteger` and validated to 0–100. The column
permits 255; the form is the constraint, matching the reference's stated range.

### Enums

`App\Enums\SnippetType` and `App\Enums\SnippetPosition`, string-backed,
following the existing `App\Enums\StatFormat`.

Each case carries its own `label()`, `icon()` and — for positions — the helper
text shown under the field. The form, the table badge and the template
definitions all read from these, so the strings exist once.

`SnippetPosition` declares its cases in document order (head → body_start →
body_end), so `cases()` yields the order the list page sorts by without a
separate sort map.

### Model

`App\Models\CodeSnippet`, casting `type` and `position` to their enums and both
flags to `boolean`.

Scope `active()` filters `is_active`. The model flushes the renderer's cache in
`booted()` on `saved` and `deleted`, mirroring `App\Models\SiteSetting`.

## Rendering

### `App\Support\SnippetRenderer`

Resolves the snippets for one request:

- Loads active snippets once per request through `App\Support\RequestCache`
  (key `code_snippets`), the same mechanism `SiteSetting::singleton()` uses.
- Groups by `position`, ordering by `priority` then `id` so equal priorities
  stay in creation order rather than an order the database happens to pick.
- When `auth()->check()` is true, excludes snippets with `skip_for_admins`.
  This is one method — `shouldSkipForCurrentUser()` — and becomes a role check
  when roles exist, without touching anything else.

Cache invalidation is `RequestCache::flush('code_snippets')` from the model, so
an editor saving a snippet sees it on the next request.

### `<x-code-snippets position="…" />`

A single Blade component, rendered at three points in **both** public layouts:

- `resources/views/components/layouts/public.blade.php`
- `resources/views/components/layouts/page.blade.php`

Six insertions total. Both layouts declare their own `<head>`, and a snippet
that appeared on one but not the other would be a confusing bug to chase, so
the mechanism is shared rather than hand-written twice.

Placement within each layout:

| position | insertion point |
|---|---|
| `head` | last element before `</head>` |
| `body_start` | first element after `<body>` |
| `body_end` | last element before `</body>` |

`head` goes last in `<head>` so a snippet cannot displace the title, meta
description or `@vite` tags.

The component emits each snippet's `code` with `{!! !!}`, separated by newlines.
It outputs nothing at all — not even whitespace — when no snippets match.

## Admin UI

`App\Filament\Resources\CodeSnippets\CodeSnippetResource` with `ListCodeSnippets`,
`CreateCodeSnippet` and `EditCodeSnippet` pages, following the structure of
`App\Filament\Resources\BlogCategories`.

Navigation placement is owned by `App\Filament\Navigation\AdminNavigation`:
`CodeSnippetsPlaceholder` is replaced by the resource in the System → System
parent, keeping its `heroicon-o-code-bracket` icon and position. The placeholder
class is deleted.

### List

Heading "Code Snippets", subheading "Inject scripts, styles, and meta tags into
your pages".

Columns: **Name** (with `description` as a secondary line), **Type** badge,
**Position** badge, **Priority**, **Active** as an inline `ToggleColumn`, and
**Updated**.

Default sort is position (document order) then priority — the order snippets
actually fire, which is the question a snippet list exists to answer. A
descending `created_at` sort would tell an editor nothing useful.

Header actions: **Help**, **Template**, **Add Snippet**.

Empty state: `heroicon-o-code-bracket`, "No snippets yet", "Add tracking codes,
analytics, or custom scripts to your site.", with **Add Snippet** and **Use
Template** actions.

### Form

One "Snippet Details" section, described "Configure where and how this code will
be injected", on a two-column grid:

| field | column | helper text |
|---|---|---|
| Name | 1 | "A descriptive name for this snippet" |
| Type | 2 | "Script, Style, Meta tag, or HTML" |
| Position | 1 | "Head: analytics, meta, CSS. Body Start: tracking pixels. Body End: chat widgets." |
| Priority | 2 | "Lower numbers load first (0-100)" |
| Code | full | "Enter the full code including tags (e.g., `<script>...</script>`)" |
| Description (Optional) | full | placeholder "Internal notes about this snippet…" |

Code is a monospace `Textarea`, rows 8.

Below the section, two toggles each in their own bordered card, matching the
reference:

- **Active** — "Enable this snippet immediately"
- **Don't load for staff/admins** — "Skip this snippet when an admin is logged
  in, so tracking scripts don't pollute analytics with staff sessions."

Validation: `name` required, max 255; `code` required; `priority` required,
integer, 0–100; `type` and `position` required and enum-backed.

### Help action

A modal explaining what each position is for and how priority orders snippets
within a position. Static content, no state.

## Templates

`App\Support\SnippetTemplates` returns the template definitions as data, so
adding one is an array entry rather than a class. Each definition has a key,
label, description, icon, and one or more snippet payloads.

| template | icon | type | produces |
|---|---|---|---|
| Google Tag Manager | `heroicon-o-tag` | `script` | **two** snippets: head + body_start |
| Google Analytics 4 | `heroicon-o-chart-bar` | `script` | one, head |
| Meta / Facebook Pixel | `heroicon-o-share` | `script` | one, head |
| Crisp Chat | `heroicon-o-chat-bubble-left-right` | `script` | one, body_end |
| Custom CSS | `heroicon-o-paint-brush` | `style` | one, head |
| Custom JavaScript | `heroicon-o-code-bracket` | `script` | one, body_end |

The **Template** action opens a modal showing these in a two-column grid.

### Templates create inactive snippets

Choosing a template creates its snippet record(s) with `is_active = false`, then
redirects to the edit form for the first one.

Every tracking template ships with a placeholder id (`GTM-XXXXXX`,
`G-XXXXXXXXXX`). If templates created *active* snippets, one click would inject
a broken tag into every page of the live site before the operator had a chance
to type their real id. Creating them switched off means nothing reaches the
public site until the operator has filled in the id and enabled it themselves.

It also resolves Google Tag Manager cleanly. GTM needs two records in two
different positions, which does not fit "pre-fill one create form" at all, but
fits "create the records, then edit" exactly.

A notification confirms what was created, naming both records for GTM.

## Testing

Feature tests, following the existing suite's conventions.

**Renderer and injection**

- Each position renders at its documented insertion point, in both layouts.
- Inactive snippets never render.
- Within a position, lower `priority` renders first; equal priorities fall back
  to `id`.
- `skip_for_admins` snippets are absent when a user is authenticated and present
  when not; snippets without the flag render in both cases.
- Code is emitted **raw** — a test asserting unescaped output, which fails if
  someone converts `{!! !!}` to `{{ }}`.
- No stray markup or whitespace when no snippets match a position.
- Nothing renders inside the Filament panel.

**Resource**

- Create, edit and delete round-trip, including enum casting.
- Priority rejects values above 100 and below 0.
- The template action creates the expected records, **inactive**, and GTM
  creates exactly two in the right positions.

**Verification.** Each test is confirmed by breaking the behaviour it names and
watching it fail, then restoring. A test suite that is green because it asserts
nothing is worse than no suite, and this project has been bitten by exactly that
before.
