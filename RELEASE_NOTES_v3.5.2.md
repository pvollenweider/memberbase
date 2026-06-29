# MemberBase v3.5.2

> Release date: 2026-06-29

## What's in this release

v3.5.2 introduces a full REST API, a 4-level permission system, inline member editing, and fixes a long-standing group filter bug caused by a Bootstrap CSS specificity conflict.

---

## REST API

Seven endpoints are now available under `/api/`:

| Endpoint | Methods | Description |
|---|---|---|
| `/api/members` | GET | Paginated member list with search and filters |
| `/api/members/{id}` | GET, POST, PATCH, DELETE | Full CRUD. PATCH sends diff-only payload — audit log records actual before/after values |
| `/api/members/{id}/groups` | GET | Member's groups with category |
| `/api/compta` | GET, POST, PATCH, DELETE | Accounting entries |
| `/api/compta-types` | GET | Accounting entry types |
| `/api/suivi` | GET | Follow-up history |
| `/api/groups` | GET | Groups with category and member count |

All endpoints return JSON and honour the application's session-based auth. Permission level (`readonly` / `user` / `manager` / `admin`) is enforced per operation.

---

## 4-level permission system

| Role | Can do |
|---|---|
| `readonly` | View members, compta, suivi, groups |
| `user` | + Add/edit entries, add members |
| `manager` | + Delete, merge, anonymize, manage groups |
| `admin` | Everything, including app settings and user management |

---

## Inline editing on member profile

The general data section switches between view and edit mode in place — no page reload. Changes are saved via a partial PATCH request and the audit log records exactly which fields changed.

---

## Group filter dropdown — bug fixed

Typing in the filter input had no visible effect. Root cause: Bootstrap declares `.d-flex { display: flex !important }`, which overrides `element.style.display = "none"` set by the filter (CSS `!important` beats inline styles). Fixed by switching to a class-based approach:

```css
.team-filterable.team-hidden { display: none !important; }
```

Additional filter improvements in this release:
- Category section dividers are now hidden alongside their header when all items are filtered out
- Typing in the filter no longer triggers the "unsaved changes" confirmation dialog

---

## Other fixes

- `wants_attestation` field added to the add-compta form
- Profile data no longer hidden in desktop view after Alpine.js timing fix
- "Expired" filter links correctly bypass htmx boost on mobile
- ColVis DataTables button no longer broken after navigation
- Alpine race condition on member profile first render fixed
- Audit diff now shows human-readable before/after values (typed strings, not raw DB values)

---

## Upgrading from v3.5.1

No database schema changes. No configuration changes required.

If you use the Docker/k8s setup, rebuild the image and redeploy. The API endpoints require Apache's `mod_rewrite` (already configured in `docker/apache.conf`).

---

## Files changed

```
23 commits, ~40 files changed
html/includes/views/users_list.php   — filter fix, wants_attestation
html/index.php                       — version bump (v3.5.2), permission checks
html/api/                            — new: REST API endpoints
html/js/member-general-form.js       — new: inline edit logic (externalized)
tests/api.spec.ts                    — new: API Playwright tests
MIGRATION_PROD.md                    — new: production deployment checklist
docker/apache.conf                   — API Directory block
CHANGELOG.md                         — this release
```
