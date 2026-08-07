# Roles and permissions

Two admin screens — Roles and Permissions — backed by a real authorization
system, replacing `RolesPlaceholder` and `PermissionsPlaceholder`.

This is the second of three specs. The first was
`2026-08-05-code-snippets-design.md`. The third is the richer Users page (stat
cards, Active/Banned status, a Roles column), which depends on everything here
and is specified separately.

## Source

Screen structure comes from the Unfold CMS demo:

- `https://demo.unfoldcms.com/admin/roles`
- `https://demo.unfoldcms.com/admin/permissions`

Its layout and modal shapes are adopted; its visual style is not, and — more
importantly — **its role and permission data is not**. Unfold seeds five roles
and permissions for features Unfold has. This application has different
features and a different operating model, so the data is derived from this
app's own navigation tree.

### What the reference gives, and what is dropped

| Reference element | Decision |
|---|---|
| Roles table: Name, Permissions, Users, Created | Kept |
| "System" badge on protected rows | Kept, driven by a real column |
| Permission count badge + first chips + `+N` overflow | Kept |
| Create Role modal: name, description, grouped checkboxes, Select All | Kept |
| Permissions table: Name, Roles, Created | Kept |
| Create Permission modal: name, Select All / Unselect All | Kept |
| Dotted permission names (`action.create`, `admin.access`) | Kept as a convention |
| **Five seeded roles** (super_admin, admin, editor, author, user) | **Dropped** — see below |
| **Unfold's permission list** (`ads.manage`, `developer.debug`, …) | **Dropped** — replaced with this app's own |

**Two roles, not five.** `author` duplicates an editor in an application whose
posts have no byline — `BlogPost` has no author column, and the news spec
dropped bylines deliberately because these are corporate posts. `user` would be
an account that exists but can enter nothing, since this application has no
front-end authentication. Roles that mean nothing are worse than absent: they
invite someone to assign one and expect an effect.

## Scope

Roles, Permissions, and the enforcement that makes them real. Explicitly
included, because without it the screens are decorative:

- `User::canAccessPanel()` gated on a permission instead of returning `true`
- Filament policies for every real resource
- Navigation filtering

Explicitly excluded: the Users page redesign (spec 3), and any front-end
authentication.

## Foundation

`spatie/laravel-permission`, with its published migrations and stock tables:
`roles`, `permissions`, `role_has_permissions`, `model_has_roles`,
`model_has_permissions`. `App\Models\User` gains the `HasRoles` trait.

The reference's shape — named roles, dotted permission strings, a
role↔permission pivot, bulk assignment — is what Spatie already models. Hand
rolling it would mean owning the permission cache, guard handling and the
team/guard edge cases that package has already solved.

### Columns Spatie does not ship

| table | column | why |
|---|---|---|
| `roles` | `description` (string, nullable) | The reference's Create Role modal has the field |
| `roles` | `is_system` (boolean, default false) | Drives the **System** badge and the delete guard |
| `permissions` | `is_system` (boolean, default false) | Same |

`is_system` is a column rather than a name check. Protecting `super_admin` by
comparing its name would stop protecting it the moment somebody renames it —
and renaming is allowed.

## Roles

Two, seeded:

| Role | `is_system` | Holds |
|---|---|---|
| `super_admin` | true | Every permission, including future ones (see below) |
| `content_editor` | false | The Content menu, Navigation Menus, and the dashboard |

`content_editor` holds exactly:

```
dashboard.view
posts.view, posts.create, posts.update, posts.delete
pages.view, pages.update
categories.view, categories.create, categories.update, categories.delete
content-blocks.view
comments.view
media.manage
menus.manage
admin.access
```

Sixteen permissions. Note what is absent: `pages.create` and `pages.delete`.
The site's structure is the administrator's; the editor fills it in. Everything
else in Content is full CRUD, because an editor who cannot publish a post is
not a content editor.

Also absent: Contacts, Users, Settings, and the entire System and
Administration groups.

### `super_admin` and future permissions

`super_admin` is granted every permission at seed time. A permission created
later — by a new feature, or by hand on the Permissions screen — is **not**
retroactively granted, which would silently leave the top role missing an
ability.

Rather than a wildcard, `super_admin` is granted new permissions explicitly:
the seeder re-syncs it on every run, and `PermissionResource`'s create action
attaches the new permission to every `is_system` role. Both paths are tested.
A wildcard bypass in `Gate::before` was considered and rejected — it makes the
Roles screen lie, showing a permission count that has nothing to do with what
the role can actually do.

## Permissions

Named `{feature}.{verb}`. The list is derived from `AdminNavigation`'s
destinations, so every entry in the sidebar has something that gates it.

**Built features, real CRUD verbs:**

```
posts.view          posts.create          posts.update          posts.delete
pages.view          pages.create          pages.update          pages.delete
categories.view     categories.create     categories.update     categories.delete
users.view          users.create          users.update          users.delete
code-snippets.view  code-snippets.create  code-snippets.update  code-snippets.delete
contacts.view                             contacts.update       contacts.delete
```

`contacts` has no `create`: messages arrive from the public site's contact
form, never from the panel.

**Built single-screen destinations:**

```
dashboard.view   media.manage   menus.manage   settings.manage
roles.manage     permissions.manage
```

**The gate** (`is_system`):

```
admin.access
```

**Placeholder screens, view only:**

```
content-blocks.view  comments.view       newsletter.view      announcements.view
advertisements.view  ad-zones.view       social-posting.view  analytics.view
email-activity.view  redirects.view      backups.view         template-pages.view
template-settings.view  translations.view  theme-editor.view
```

Fifteen of these gate a screen that currently says "not built yet". They are
included because the sidebar deliberately shows the product's full intended
shape, and a permission list that covered only built features would have to be
edited every time a placeholder becomes real. The trade is that a large part of
the list gates nothing today; the Permissions screen makes no distinction, and
should not — from an operator's seat these are simply screens.

Roughly 45 permissions in total, paginating to five pages at the reference's
ten-per-page.

## Enforcement

Three layers. Any one alone leaves a hole.

### 1. Panel access

`User::canAccessPanel()` returns `$this->can('admin.access')`, replacing
today's unconditional `true`.

### 2. Policies

A policy per real resource — `BlogPost`, `Page`, `BlogCategory`,
`ContactMessage`, `User`, `CodeSnippet`, `Role`, `Permission` — mapping
Filament's `viewAny`/`create`/`update`/`delete` to the matching permission.
This is what makes a typed URL 403 rather than render.

Filament's `Resource::canCreate()` consults the policy automatically, so the
Pages resource hides its Create button for `content_editor` without a special
case.

### 3. Navigation

`AdminNavigation` builds the sidebar with a `NavigationBuilder`, which makes
Filament **skip auto-registration entirely** — its own docblock says so. The
consequence is that Filament's policy-based nav hiding does not apply here:
without an explicit check, a `content_editor` sees Users, Settings and every
System link, clicks one, and hits a 403.

Each entry declares the permission it needs, and is filtered out when the
current user lacks it. A parent whose children are all filtered away is removed
too — an empty disclosure triangle is worse than no entry.

## The lockout problem

The moment `canAccessPanel()` checks a permission, every existing account is
locked out, because none of them has a role. There are three real accounts on
this installation.

A data migration grants `super_admin` to every existing user, so nobody's
access changes when this deploys. Demotion is then a deliberate act on the
Users screen.

**The migration must not mask a broken gate.** A test asserts that a user with
no roles is refused, independently of the migration — otherwise a gate that
always returned `true` would look identical in every test, and the failure
would surface only when somebody was demoted.

### Three guards against bricking the admin from inside the UI

| Guard | Without it |
|---|---|
| An `is_system` role or permission cannot be deleted | Deleting `admin.access` locks out everyone, permanently |
| The last user holding `super_admin` cannot be demoted or deleted | The panel keeps working until that session expires, then nobody can administer anything |
| `admin.access` cannot be detached from `super_admin` | Same as the first, by a different route |

Each guard refuses with a clear message naming why, and each has a test that
attempts exactly the bricking scenario.

## Admin UI

Both screens are **simple resources** — an index page, with create, edit and
delete in modals. That matches the reference, where both Create dialogs are
modals rather than routed pages.

Navigation placement stays owned by `AdminNavigation`:
`RolesPlaceholder` and `PermissionsPlaceholder` are replaced by the two
resources in the Users Management parent, keeping their icons and order. Both
placeholder classes are deleted.

### Roles

Heading "Roles Management", subheading "Manage user roles and their
permissions". A single "Add Role" header action.

| column | content |
|---|---|
| Name | the role name, with a **System** badge when `is_system` |
| Permissions | a count badge ("22 permissions"), then the first three permission chips, then `+N` |
| Users | a people icon and the assigned-user count |
| Created | date |

Row actions: Edit and Delete in a `…` menu, Delete hidden when `is_system`.

The create/edit modal, per the reference: **Role Name**, **Description
(Optional)**, then a Permissions section headed "Select permissions to assign
to this role" with a **Select All** toggle. Checkboxes are grouped by the
segment before the dot — Posts, Pages, Categories, and so on — because a flat
list of forty-five is unusable.

### Permissions

Heading "Permissions Management", subheading "Manage user permissions and their
assignments". A single "Add Permission" header action.

| column | content |
|---|---|
| Name | the dotted name, with a **System** badge when `is_system` |
| Roles | a count badge ("4 roles"), then role chips, then `+N` |
| Created | date |

The create modal: **Name** (placeholder `e.g., manage.users`), then **Assign to
Roles** with **Select All** and **Unselect All** buttons over a checkbox list.

## Seeding

`RolesAndPermissionsSeeder` creates every permission and both roles through
`firstOrCreate`, then re-syncs `super_admin` to hold all of them. Idempotent:
re-running neither duplicates nor strips.

It joins the `DatabaseSeeder` chain, which now actually calls its seeders.
Order matters only in that it must run before anything that assigns a role.

## Testing

- **The gate:** a user with no roles is refused; `content_editor` and
  `super_admin` are admitted. Asserted without relying on the migration.
- **The editor's limits:** `content_editor` cannot create or delete a page but
  can edit one; gets 403 on `/superduper/users` by direct URL, not merely a
  hidden link; cannot reach Settings or any System screen.
- **Navigation:** the rendered sidebar for a `content_editor` contains Content
  and Navigation Menus and does not contain Users, Settings or System — checked
  against rendered navigation, not policy return values, because the
  `NavigationBuilder` is exactly what bypasses policy-based hiding.
- **The three guards:** each bricking scenario is attempted and refused.
- **`super_admin` completeness:** a permission created after seeding is held by
  `super_admin`, by both paths (seeder re-run, and the Permissions screen).
- **Seeder idempotency:** a second run neither duplicates nor strips.
- **The migration:** pre-existing users come out holding `super_admin`.

**Verification.** Every test is confirmed by breaking the behaviour it names
and watching it fail. An authorization suite that passes because everything
returns `true` is indistinguishable from one that passes because the rules
work — and this project has shipped tests that asserted nothing before.
