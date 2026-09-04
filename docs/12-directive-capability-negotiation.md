# 12 - Directive Capability Negotiation

[← Previous: Example: Phone OTP Verification & Auth Workflow](11-example-otp-verification-workflow.md) | [📚 Docs Index](README.md) | [Next: Interactive Workflow Manager →](13-interactive-workflow-manager.md)

---

> **Status: implemented.** This is the contract as shipped — the
> `workflow_directives` manifest, the `DirectiveNegotiator`, the `client` /
> `capabilities` request fields, the `/directives` endpoint, negotiation
> telemetry on `workflow_logs`, and the admin Directives module are all in the
> package. Enable/disable via the `negotiation.enabled` config flag.

## The problem

Workflows return **directives** (`toast`, `mutate_cart`, `open_sheet`, `navigate`,
`haptic`, …) that the client interprets and renders. Today this is an *implicit*
contract: the server assumes every client understands every directive it emits.

That assumption breaks in two everyday situations:

1. **You add a new directive type.** You ship `open_ar_view` server-side, but the
   app version already installed on a million phones has never heard of it.
2. **Multiple client versions are live at once.** Web is on v3, iOS on v2.4,
   Android split across v2.1–v2.6 — each supports a different subset.

When a client receives a directive it can't render, the best case is a silent
no-op (the user just doesn't get the feature) and the worst case is a crash. In
both cases the server has **no idea it happened**.

The solution is **capability negotiation**: the client tells the server what it
can render, the server emits only that, and anything unknown degrades gracefully
instead of failing — with every gap logged so you can see it.

---

## The four mechanisms

| # | Mechanism | Solves |
|---|---|---|
| 1 | **Directive manifest** — a server-side registry of every directive, its per-platform minimum version, payload schema, and fallback. | "What exists, and who can render it." Single source of truth. |
| 2 | **Negotiation at execute time** — client sends `platform` + `app_version`; the engine filters/downgrades directives to the supported set. | The client never receives a directive it can't handle. |
| 3 | **Graceful degradation on the client** — an unknown directive type is *ignored, never fatal*. | Forward compatibility: tomorrow's directive can't brick today's app. |
| 4 | **Telemetry** — every dropped or downgraded directive is written to `workflow_logs`. | Visibility: capability gaps become a query, not a guess. |

They stack. The 80/20 minimum is #3 + #4 (stop crashing, gain visibility) with
almost no server work; #1 + #2 turn "fail safely" into "adapt intelligently."

---

## The `workflow_directives` table

The manifest. One row per directive type, following the same conventions as the
`workflows` table (site-scoped, `publish_status`, audit columns, soft deletes).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_directives')) {
            Schema::create('workflow_directives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_id')->default(1)->index();
                $table->string('type');                      // canonical name: 'mutate_cart'
                $table->string('label');                     // human name for admin UI
                $table->string('category')->nullable();      // 'cart' | 'navigation' | 'feedback'
                $table->text('description')->nullable();
                $table->json('platforms')->nullable();       // { "web":"1.0", "android":"2.1", "ios":"2.1" }
                $table->json('schema')->nullable();          // payload field spec (used for validation)
                $table->string('fallback')->nullable();      // another directive `type` to substitute
                $table->boolean('is_core')->default(false);  // package-shipped vs app-registered
                $table->boolean('publish_status')->default(true);
                $table->unsignedBigInteger('insert_by')->nullable();
                $table->unsignedBigInteger('update_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['site_id', 'type']);         // type is unique *within a site*
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_directives');
    }
};
```

### Column reference

| Column | Purpose |
|---|---|
| `site_id` | Multisite scope, default `1`, indexed — identical to `workflows`. A directive belongs to a site; resolution falls back to the master site (see below), so the common case is "define once on the master site, override per site only when needed." |
| `type` | The canonical directive name and natural key — `mutate_cart`, `toast`, `haptic`. Unique **per site** (`site_id` + `type`), so seeders upsert by it. |
| `label` / `category` / `description` | Admin editor ergonomics — render a grouped, searchable directive picker instead of a free-text field. |
| **`platforms`** | The compatibility matrix in one column: a map of `platform → minimum app_version that supports this directive`. `null`/empty = supported on all platforms, any version. |
| **`schema`** | Field spec for the directive's payload. Powers admin-editor validation, optional server-side validation, and generated client typings. |
| **`fallback`** | The `type` of another directive to substitute when a client can't render this one (`open_ar_view → navigate`). Self-referential by `type`; resolved as a chain (see below). |
| `is_core` | Distinguishes package-shipped directives from ones a host app registers, so `db:seed` re-runs upsert only core rows and never clobber app-added ones. |
| `publish_status` / `deleted_at` | Enable/disable or retire a directive without losing history — same lifecycle as `workflows`. |
| `insert_by` / `update_by` | Audit columns, matching the rest of the package. |

---

## Multisite resolution

Directives resolve **per site with a master-site fallback** — the same rule the
engine already uses for workflow resolution (see the site-specific resolution
introduced for `workflows`). For an incoming request on `site_id = N`:

1. Prefer the directive row where `site_id = N`.
2. If none exists for `type`, fall back to the row on the master site
   (`config('hashtagcms-workflows.master_site_id')`, default `1`).

This means:

- **Define once, everywhere.** Seed the core catalog onto the master site and
  every site inherits it.
- **Override where it matters.** A white-label site that must disable `haptic`,
  or ship a bespoke `open_store_locator`, adds/edits only its own rows — no
  separate overrides table, no per-site duplication of the full catalog.

```php
// Effective directive set for (site, type), master-site fallback
$masterSiteId = (int) config('hashtagcms-workflows.master_site_id', 1);

$directives = WorkflowDirective::query()
    ->where('publish_status', true)
    ->whereIn('site_id', [$siteId, $masterSiteId])
    ->get()
    // site-specific row wins over the master-site row for the same type
    ->groupBy('type')
    ->map(fn ($rows) => $rows->firstWhere('site_id', $siteId) ?? $rows->first());
```

---

## Capability resolution

Because each directive carries its own per-platform minimum version, the
**supported set is computed from the manifest alone** — no separate "client
profile" table to maintain. Given the resolved directive set for the site plus
the client's `platform` + `app_version`:

```php
$client = ['platform' => 'android', 'app_version' => '2.3.0'];

$supported = $directives->filter(function ($d) use ($client) {
    $min = data_get($d->platforms, $client['platform']);
    // no entry for this platform, or null platforms = universally supported
    return $min === null || version_compare($client['app_version'], $min, '>=');
})->keyBy('type');
```

Updating a compatibility rule is a single-row edit — no client release, no
profile-table drift.

---

## The negotiation pass

One new step in `GenericWorkflowEngine::execute()`: after the `WorkflowResponse`
is built, rewrite its directive list against the supported set before returning.
For each emitted directive:

- **Supported** → pass through unchanged.
- **Unsupported but has a `fallback`** → replace with the fallback directive,
  chasing the chain (`A → B → C`) until it lands on a supported type. Guard
  against cycles and cap the depth.
- **Unsupported, no usable fallback** → drop it, and record the drop.

```
emitted:  [ mutate_cart, open_ar_view, toast ]
client:   android 2.3.0   (open_ar_view needs android 3.4)

resolve:  mutate_cart  ✓ supported        → keep
          open_ar_view ✗ → fallback navigate ✓ → substitute
          toast        ✓ supported        → keep

returned: [ mutate_cart, navigate, toast ]
dropped:  [ ]         downgraded: [ open_ar_view→navigate ]
```

The client only ever receives directives it can actually render.

---

## Telemetry

`workflow_logs` already records every execution. Extend the logged payload with
what negotiation changed:

```json
{
  "directives_downgraded": [
    { "from": "open_ar_view", "to": "navigate" }
  ],
  "directives_dropped": [],
  "client": { "platform": "android", "app_version": "2.3.0" }
}
```

Now "which client versions can't render `open_ar_view`, and how often" is a
`workflow_logs` query — the single most useful signal for deciding when an old
directive is safe to require, or when a client rollout has caught up.

Clients should also **echo back** any directive type they received but didn't
recognize (a belt-and-suspenders complement to server-side negotiation), so gaps
that slip past the manifest still surface.

---

## Client contract

Two rules, both cheap, both load-bearing:

1. **Send identity.** Include `client: { platform, app_version }` in the execute
   request so the server can negotiate. Optionally send an explicit
   `capabilities: [ "toast", "mutate_cart", … ]` array to override version-based
   resolution (useful for feature flags or A/B builds).
2. **Ignore unknowns, never crash.** Any directive `type` the client doesn't
   recognize is skipped silently and reported via telemetry — never fatal. This
   is the forward-compatibility guarantee that lets you ship new directives
   without waiting for every client to update.

### Request shape

```json
{
  "workflow": "WORKFLOW_APPLY_COUPON",
  "payload": { "code": "DOM50" },
  "site_id": 1,
  "client": { "platform": "android", "app_version": "2.3.0" }
}
```

`platform` at the top level remains accepted for backward compatibility; `client`
is the richer, preferred form.

---

## The manifest API

Expose the resolved manifest so clients can self-configure,
sitting alongside the existing `/execute` and `/health` routes:

```
GET /api/hashtagcms/public/workflows/v1/directives?site_id=1&platform=android&app_version=2.3.0
```

- With no query params: the full catalog for the resolved site.
- With `platform` + `app_version`: pre-filtered to that client's supported set
  (handy for a client that wants to cache "what can I render" at startup).

Response:

```json
{
  "success": true,
  "directives": [
    {
      "type": "mutate_cart",
      "label": "Mutate cart",
      "category": "cart",
      "platforms": { "web": "1.0", "android": "2.0", "ios": "2.0" },
      "schema": { "action": "string", "couponCode": "string?", "discountPercent": "int?" },
      "fallback": "toast"
    }
  ]
}
```

---

## Author-time validation

Because the manifest exists, the **admin editor can validate a
workflow at save time** rather than at crash time:

- Flag any directive a workflow emits that isn't in the manifest ("unknown
  directive `open_sheeet` — typo?").
- Warn when a workflow relies on a directive that isn't supported on all target
  platforms ("`open_ar_view` is unsupported on Web and on Android < 3.4 — add a
  `fallback` or it will be dropped for those clients").
- Validate each directive's payload against its `schema`.

This closes the loop: capability mismatches are caught by the author, negotiated
by the server, degraded by the client, and measured in the logs.

---

## Example seed rows

```json
[
  {
    "type": "toast",
    "label": "Toast message",
    "category": "feedback",
    "platforms": null,
    "schema": { "message": "string", "level": "enum:success,error,info,warning" },
    "fallback": null,
    "is_core": true
  },
  {
    "type": "mutate_cart",
    "label": "Mutate cart",
    "category": "cart",
    "platforms": { "web": "1.0", "android": "2.0", "ios": "2.0" },
    "schema": { "action": "string", "couponCode": "string?", "discountPercent": "int?" },
    "fallback": "toast",
    "is_core": true
  },
  {
    "type": "haptic",
    "label": "Haptic feedback",
    "category": "feedback",
    "platforms": { "android": "1.0", "ios": "1.0" },
    "schema": { "intensity": "enum:success,error,warning,medium" },
    "fallback": null,
    "is_core": true
  },
  {
    "type": "open_ar_view",
    "label": "Open AR preview",
    "category": "navigation",
    "platforms": { "ios": "3.2", "android": "3.4" },
    "schema": { "modelUrl": "string" },
    "fallback": "navigate",
    "is_core": true
  }
]
```

---

## Rollout phases

| Phase | Work | Value |
|---|---|---|
| **1 — Fail safely** | Client rule: ignore unknown directives. Log unknown/echoed types to `workflow_logs`. | No more crashes; first visibility. No schema change. |
| **2 — The manifest** | `workflow_directives` table + `WorkflowDirective` model + `WorkflowDirectivesSeeder` (core rows on the master site) + `GET /directives`. | Single source of truth; clients can self-configure. |
| **3 — Negotiate** | Resolution + filter/fallback/drop pass in `GenericWorkflowEngine`; downgrade/drop telemetry. | Server adapts output per client; gaps measured. |
| **4 — Author guardrails** | Admin-editor validation against the manifest. | Mismatches caught before release. |

Each phase is additive and independently shippable; nothing in phase 1–2 breaks
the current `/execute` contract.

---

## Open decisions

- **Version scheme.** `platforms` values assume SemVer-comparable strings
  (`version_compare`). If any client uses build numbers or dates, normalize to a
  comparable form before storing.
- **Fallback depth.** Cap the fallback chain (e.g. 3 hops) and treat a cycle as
  "drop + log" rather than looping.
- **Explicit `capabilities` vs. version resolution.** Support both, but define
  precedence: an explicit `capabilities` array, when present, overrides
  version-based resolution for that request.

---

[← Previous: Example: Phone OTP Verification & Auth Workflow](11-example-otp-verification-workflow.md) | [📚 Docs Index](README.md) | [Next: Interactive Workflow Manager →](13-interactive-workflow-manager.md)
