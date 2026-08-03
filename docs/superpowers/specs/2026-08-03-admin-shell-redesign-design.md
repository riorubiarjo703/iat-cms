# Admin Shell Redesign — Design

**Date:** 2026-08-03
**Status:** Approved (design), pending implementation plan
**Project:** `iat-cms` — Laravel 13.23, Filament 5.7.4, PHP 8.4.16, PostgreSQL, Vite 8
**Reference:** `~/Downloads/cms-dashboard-design-spec.md` and its accompanying screenshot

## Goal

Restyle the Filament admin panel to match the reference design — its palette, spacing,
navigation structure and dashboard composition — **without putting anything in front of an
editor that does not work.**

## Scope, stated plainly

The reference design names roughly 25 destinations. Seven exist. This slice delivers the
**shell**: theme, navigation structure, top bar and dashboard. It does **not** build the
eighteen missing subsystems the reference sidebar implies.

The reference also suggests React + Tailwind with `recharts`. That is not adopted: Filament 5
already provides every capability the design needs, and rebuilding in React would discard six
resources, two singleton pages and a curated sidebar that are built and tested. Verified against the
installed source at exact locations, not assumed:

| Capability | Location |
|---|---|
| Nested child items | `filament/filament/src/Navigation/NavigationItem.php` `childItems()` |
| Count badges | same file, `badge()` line 81 |
| Collapsible groups | `filament/filament/src/Navigation/NavigationGroup.php` `collapsible()` line 57 |
| Sidebar collapse | `Panel/Concerns/HasSidebar.php` `sidebarCollapsibleOnDesktop()` |
| Global search | `Panel/Concerns/HasGlobalSearch.php` |
| Custom theme | `php artisan make:filament-theme` |

## The problem this design solves honestly

Every metric the reference dashboard asks for is unavailable:

| Reference element | Reality |
|---|---|
| Visitors today, Pageviews, Avg. session, Bounce rate | No analytics tracking exists |
| Total Comments, Subscribers | No comments or newsletter system |
| Traffic Overview chart | No data |
| Activity feed | No audit log table |
| Published Posts, Pages, Media files | All currently `0` |

Built literally, the dashboard would read `2, 0, 0, 0` above an empty chart and an empty feed —
worse than Filament's default, and indistinguishable from broken.

So the dashboard keeps the reference's **visual composition** but is populated with
**content-health metrics that are queryable today and stay meaningful as the CMS grows.**

## Decisions

| Decision | Choice | Rejected |
|---|---|---|
| Stack | Restyle the existing Filament panel | Rebuild the admin in React |
| Sidebar contents | Real destinations only; grows as features land | Scaffold all 25 with placeholder screens |
| Dashboard data | Content-health metrics from real counts | Literal spec metrics showing zeros; honest empty states for every unavailable tile; minimal hero-only dashboard |
| Analytics | Out of scope, tracked as a later slice | Build tracking first |
| Activity feed | Omitted this slice — no audit log exists | Add an activity log package now |

## Visual language

Taken from the reference spec, applied via a custom Filament theme:

| Token | Value |
|---|---|
| Primary accent | `#3B82F6` → `#2563EB` |
| Positive delta | `#16A34A` |
| Negative delta | `#DC2626` |
| Warning / pending | `#F59E0B` |
| Page canvas | `#F9FAFB` |
| Card surface | `#FFFFFF` |
| Borders | `#E5E7EB` |
| Primary text | `#111827` |
| Muted text | `#6B7280` |
| Card radius | 12–16px |
| Control radius | 8–10px |
| Typography | Inter; 28–32px greeting, 16–18px card titles, 24–28px stat numbers, 13–14px body, 11–12px uppercase eyebrows |

Flat, border-separated surfaces — no heavy drop shadows. Icons are thin-stroke line icons at
18–20px, which is Heroicons' outline set, already what the panel uses.

**A constraint that already bit once:** a Filament `NavigationGroup` and its items cannot both
carry icons — Filament throws at render. Icons go on items only.

## Navigation

Two levels: non-clickable uppercase section headers, and items beneath them. Parents with
children expand to an indented sub-list and stay visually active while expanded.

```
GENERAL
├─ Dashboard
├─ Content ▾
│   ├─ Pages
│   ├─ Blog Posts            badge: draft + scheduled count
│   ├─ Blog Categories
│   └─ Media Library
└─ Contacts                  ← omitted: no contact system yet

APPEARANCE
├─ Navigation Menus ▾
│   ├─ Public Menu
│   └─ Admin Topbar Menu
└─ Site Settings

SYSTEM
└─ Users
```

Differences from the reference, each deliberate:

- **Contacts, Marketing, Roles, Permissions, Analytics, Email Activity, SEO/Redirects, Code
  Snippets, Backups, Template Settings, Theme Editor, Comments** — omitted. Nothing behind them.
- **Media Library** — present, though absent from the reference sidebar. It exists and is used.
- **Content Blocks** and **Translations** — omitted here; both arrive with the Graper page
  builder slice, whose spec already covers them. They slot into `Content` and `Appearance`
  respectively when built.
- **Blog** is folded into `Content` rather than being its own group. The owner's earlier
  preferred structure had a separate `Blog` group containing a Blog Dashboard and Authors —
  those arrive in slice A2, at which point `Blog` becomes its own group as originally asked.

`AdminNavigation` remains the sole owner of the sidebar, so every item is declared in one file.
The accepted trade-off is unchanged: a resource added later must be registered there or it will
not appear.

## Top bar

Filament provides all of it; this is configuration, not construction.

- Sidebar collapse toggle — `sidebarCollapsibleOnDesktop()`
- Global search with `⌘K` — `globalSearch()`, with searchable attributes declared on the Pages,
  Posts and Media resources so the field returns something useful rather than nothing
- Theme toggle — Filament's built-in light/dark switch
- User menu with avatar initials

The reference's "Pro" badge and help icon are omitted: one is a vendor upsell, the other has no
destination.

## Dashboard

Four regions, matching the reference's composition.

### 1. Welcome banner

Full-width card, subtle blue-to-white gradient, rounded, generous padding.

- Uppercase eyebrow: today's date
- Greeting: "Good morning/afternoon/evening, **{first name}**", name in accent blue
- Primary CTA top-right: "New Page"
- A row of four inline figures replacing the reference's analytics: **languages configured**,
  **menu items**, **users**, **drafts pending** — all queryable, all meaningful

Time-of-day greeting uses the application timezone, not the server's.

### 2. Stat cards

Four equal cards, each a tinted rounded icon tile above a muted label and a large figure:

| Card | Source |
|---|---|
| Pages | count of Graper pages |
| Posts | published count, with draft + scheduled as an amber "N pending" pill |
| Media files | media manager file count |
| Users | user count |

The pending pill mirrors the reference's "22 pending" treatment on Comments, applied where a
real pending state exists.

### 3. Translation coverage

Replaces the reference's Traffic Overview, occupying the same two-thirds width. A horizontal
bar per configured locale showing what percentage of translatable content carries a non-empty
value for that locale.

Coverage is computed generically: walk every model composing `HasTranslatableFields`, read its
`TRANSLATABLE` constant, and count filled versus total leaves per locale. Nothing hardcodes a
model list, so the panel keeps working as models come and go.

This is the one panel that is genuinely useful today — the project ships trilingual content and
has no way to see what is untranslated. It is also honest about its own limits: after the Graper
slice, page copy moves into `page_translations` and this calculation will need extending, which
is noted rather than discovered later.

### 4. Quick actions

Six bordered cards, tinted icon above a bold label: **New Page, New Post, Media Library,
Navigation Menus, Site Settings, Users**. Each links to a real create-or-index route.

### Not built this slice

The activity feed and the footer summary bar. The feed has no audit log to draw on, and
inventing one from `updated_at` timestamps would produce a plausible-looking feed that silently
misses deletions and attributes changes to the wrong actor. Better absent than misleading.

## Architecture

| Unit | Responsibility |
|---|---|
| `resources/css/filament/admin/theme.css` | Design tokens; the only place colours and radii are defined |
| `App\Filament\Navigation\AdminNavigation` | The whole sidebar, extended with nested child items and badges |
| `App\Support\ContentHealth` | All dashboard queries; no Blade or Filament knowledge |
| `App\Support\TranslationCoverage` | Per-locale coverage, computed from `HasTranslatableFields` |
| `App\Filament\Widgets\*` | One widget per dashboard region, each reading from the two support classes |
| `App\Filament\Pages\Dashboard` | Composes the widgets; holds no queries |

Keeping every query in `ContentHealth` and `TranslationCoverage` means the dashboard's data is
testable without rendering a single widget, and a widget cannot quietly introduce an N+1.

## Error handling

| Condition | Behaviour |
|---|---|
| A counted model's table is missing (e.g. after slice A1 removals) | The tile reports `—` and logs once; the dashboard still renders |
| Media manager absent or its migrations unrun | Media tile shows `—` rather than throwing |
| No translatable models found | Coverage panel renders an explanatory empty state |
| A locale has no translatable content at all | Shown as `—`, not `0%`, which would imply failure rather than absence |
| Fresh database, all counts zero | Renders normally; zero is a legitimate value here, unlike the reference's analytics |

## Testing

Unit:
- `ContentHealth`: each count against seeded fixtures; missing-table path returns `null` and logs
- `TranslationCoverage`: full, partial and empty coverage; a model with no translatable fields is
  skipped; adding a locale to `SiteSetting::LOCALES` changes the output without code changes

Feature:
- The dashboard renders for an authenticated user on a fresh database
- Every quick-action link resolves to a real registered route — this is the assertion that would
  have caught a sidebar item pointing at the wrong resource
- The sidebar exposes exactly the declared items, no duplicates, in the declared group order
- Every nav item's destination matches its expected resource URL
- Badge counts reflect real pending records and are absent when there are none

Browser (manual):
- The panel matches the reference's palette, radii and spacing
- Nested nav items expand and collapse; the parent stays active while expanded
- `⌘K` opens global search and returns results
- The sidebar collapses and restores

## Out of scope

Analytics tracking and the traffic chart; the activity feed and its audit log; comments;
newsletter and the whole Marketing group; roles and permissions; redirects; code snippets;
backups; template settings; theme editor. Each is a later slice, and the sidebar gains its entry
when the feature behind it exists — not before.
