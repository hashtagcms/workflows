# Changelog

All notable changes to `hashtagcms/workflows` are documented here. This project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- **SSO / external-login provider module (data-driven).** A new admin module
  (**Workflows → SSO Providers**, `admin/workflows/sso`) manages per-site
  providers that verify a client credential and resolve it to a workflow
  identity — no code required. Backed by the `workflow_sso_providers` table
  (site + master-site fallback, `alias` unique **per site**, `driver`, `enabled`,
  `on_failure`, `cache_ttl`, and a `config` JSON) and the `SsoIdentityResolver`:
  - **`opaque` driver** — introspects the token against the login service (e.g.
    `https://login.example.com/sso/authenticate`) by reusing the `HttpTargetAdapter` +
    `VariableInterpolator` request/response shape: a `config.verify` block is the
    request formatter (`{{request.bearer_token}}`), a `config.identity` block maps
    the response (`{{response.body.*}}`) to `{ user_id, claims }`. Verified tokens
    are cached per token-hash for `cache_ttl` seconds; failures are never cached.
  - **Configurable credential source** — the token is read from
    `Authorization: Bearer` by default, but a provider may point at any header via
    a `credential` block (e.g. `{ "header": "sessiontoken", "strip_prefix": "Bearer " }`),
    for APIs that don't use `Authorization`. When set it is authoritative (no
    fallback); incoming headers can also be forwarded to the validate endpoint via
    `{{request.headers.*}}`.
  - **`jwt` driver** — verifies the token signature locally against the provider's
    JWKS (`{{token.*}}` mapping), no per-request call. Requires the optional
    `firebase/php-jwt` package (composer `suggest`); a `jwt` provider without it
    raises a clear, actionable error. The `opaque` driver needs nothing extra.
  - **`on_failure` policy** — `reject` surfaces a failed identity (for the caller
    to turn into a 401), `anonymous` runs the workflow unauthenticated. When no
    provider is configured for a site, resolution falls back to the local guard,
    so nothing regresses.
  - **Enforcement** — `Workflows::execute()` now blocks a run (returning a 401 the
    execution API maps to HTTP 401, logged as unsuccessful) when the credential
    was rejected, or when a workflow's existing `auth_required` flag is set and no
    identity resolved. The SSO resolver is swapped in over the local guard
    automatically, only when the provider module is active (table present + a
    provider enabled) — so non-SSO installs pay nothing.
  - **Per-workflow provider pin** — a workflow may name a specific provider via a
    new optional `workflows.sso_provider_alias` column (migration `000010`);
    otherwise identity is resolved by the site's default provider. The site
    default is now deterministic (site-over-master, then lowest id), and a stale
    pin degrades gracefully to that default. The Workflow Manager gains an
    *Identity provider (SSO)* picker (concrete providers only — an unpinned
    workflow defaults to the site's default provider, shown selected) with a live
    indicator that states which provider a workflow will use, or that SSO is
    ignored. A workflow can also opt out of SSO entirely by choosing **None**
    (stored as `@none` /
    `SsoIdentityResolver::PROVIDER_NONE`) — identity is then resolved by the local
    guard even while a provider is enabled, for providers created ahead of use.
    `WorkflowIdentityResolver::resolve()` gains an optional `$ssoProviderAlias`
    argument (ignored by the local resolver).
- **Pluggable workflow identity (step 1 of SSO support).** The engine no longer
  hard-couples the executing user to Laravel's login. `Workflows::execute()` now
  resolves identity through a `WorkflowIdentityResolver` contract instead of
  calling `auth()` directly, and accepts an explicit `identity:` argument that
  overrides resolution (a scalar id, a user model, or a `WorkflowIdentity`). The
  default binding (`AuthIdentityResolver`) wraps `auth()`, so apps using the
  local guard are unaffected; apps fronted by an external login service rebind
  the contract. A normalized `WorkflowIdentity` value object routes integer ids
  to local users and string ids to external subjects, and never throws when no
  identity is present (workflows still run anonymously). Foundation for the
  forthcoming data-driven SSO provider module.
  - Providers may also map an **opt-in raw passthrough** — `identity.raw`
    (e.g. `"raw": "{{ response.body.data }}"`) — exposed to workflows as
    `{{ identity.raw.* }}` for when curating every field isn't wanted. Curated
    `claims` stays the default (it decouples workflows from the provider's
    response shape); `raw` deliberately reintroduces that coupling. The
    `{{ identity.* }}` namespace also exposes `user_id`, `external_user_id`, and
    `provider`.
  - `WorkflowContext` now carries the resolved identity and its claims:
    `getExternalUserId()` reaches a non-local (SSO/UUID) subject, `getIdentity()`
    returns the full `WorkflowIdentity`, and `getClaims()` / `claim()` expose
    normalized claims (roles, tenant, …). `getUserId()` still returns the local
    integer id only. Declarative workflows can interpolate `{{ claims.* }}`
    (e.g. `{{ claims.roles }}`) alongside the existing `{{ user.* }}`. All
    additive — existing handlers and contexts are unaffected.
  - `workflow_logs` gains `external_user_id` (indexed) and `sso_provider_alias`
    (new non-destructive migration). `user_id` still holds the local integer id;
    an external subject is logged under `external_user_id` with the resolving
    provider, so audit trails stay complete when login is handled elsewhere.
- **`workflows:check-java-parity` command** — reports when the Java port
  (github.com/hashtagcms/workflows-java) has fallen behind this reference
  implementation's directive manifest. Compares the manifest against the Java
  repo's checked-in fixture and lists directives added/removed/changed here that
  Java hasn't picked up (non-zero exit on drift, for CI); `--write` updates the
  fixture from PHP in one step.
- **Declarative workflows can return `data`.** A `data` object under `on_success`
  / `on_failure` (or top-level) is interpolated and returned in the response's
  `data` field — the natural way to surface a target's response, e.g.
  `"data": { "items": "{{ response.body }}" }`. Previously only PHP handlers could
  populate `data`.
- **Interactive Workflow Manager (experimental)** — a new admin module
  (`admin/workflows/builder`) with a Vue 3 visual editor that reads/writes the
  **same `workflows` rows** as the classic manager (fully interchangeable):
  - **Validation builder** (rule rows), **target builder** (HTTP / service /
    event / none with conditional fields, header/query editors, bearer auth),
    and **on-success / on-failure directive builders**.
  - Directive builders are **manifest-driven** (fetch `/directives`): a colourful,
    category-grouped palette; per-directive fields generated from each
    directive's `schema`; **drag-to-reorder** cards; and interpolation-token
    insert helpers.
  - **Live preview** — runs the current unsaved config through the engine +
    capability negotiation (`builder/preview`) and renders the returned
    directives.
  - **Visual ⇄ JSON** toggle (bidirectional, validated) so nothing is ever
    trapped.
  - Build pipeline: webpack (no Vite), Vue 3 bundled, `vuedraggable`; the
    compiled bundle ships in `resources/dist` and is served from a whitelisted
    package route (no `vendor:publish` needed). Gated by `builder.enabled`
    (env `HASHTAGCMS_WORKFLOWS_BUILDER`); the classic JSON editor is untouched.
- **`make:workflow` command** — scaffolds a new handler in `app/Workflows`
  implementing `WorkflowHandlerInterface`, deriving an alias from the class name
  (overridable with `--alias`) and printing the line to register it.
- **`hashtagcms-workflows:publish-examples` command** — copies the four example
  commerce handlers (AddToCart, ApplyCoupon, QuickReorder, SubmitFeedback) into
  `app/Workflows/Examples` as editable starting points, and prints their
  register lines.
- **Directive capability negotiation** — the server now emits only directives a
  given client can render (see `docs/12-directive-capability-negotiation.md`):
  - `workflow_directives` table + `WorkflowDirective` model: a site-scoped
    manifest declaring each directive's per-platform minimum version, payload
    schema, and fallback. Core directives are seeded on install (master site).
  - `DirectiveNegotiator` engine: filters the response's directive list against
    the manifest, downgrading unsupported directives to their `fallback` (chased
    as a chain) or dropping them. Fail-safe — unknown types and an empty manifest
    pass straight through.
  - Execute API accepts `client: { platform, app_version }` and an explicit
    `capabilities` array (legacy top-level `platform` still honoured).
  - `GET /api/hashtagcms/public/workflows/v1/directives` returns the manifest,
    optionally pre-filtered to a client via `platform` + `app_version`.
  - Negotiation telemetry (`client_platform`, `client_app_version`, and the
    downgraded/dropped directives) recorded on `workflow_logs`.
  - Admin **Workflow Directives** module (`admin/workflows/directives`) for
    managing the manifest, plus `WorkflowDirectivesSeeder` to re-seed core rows.
  - A catalog of **72 predefined directives** across feedback, navigation, cart,
    content, state, auth, device, analytics, payments, flow, and growth
    categories, seeded on install.
  - `negotiation.enabled` config flag (env `HASHTAGCMS_WORKFLOWS_NEGOTIATION`).
- **Complete install with one command.** `php artisan migrate` now provisions the
  whole package end-to-end: the admin modules (Workflows, Manager, Logs,
  Directives), the directive manifest, and the bundled example
  workflows — no separate `db:seed` step. Opt out of the demo workflows with
  `install.seed_examples=false` (env `HASHTAGCMS_WORKFLOWS_SEED_EXAMPLES`).

- Bundled example workflow seeders demonstrating every structural pattern, all
  runnable and upserted by alias:
  - `LoadPhotosWorkflowSeeder` / `LoadPhotosPaginatedWorkflowSeeder` — HTTP GET and
    query-param pagination against a public REST API (picsum.photos).
  - `WorkflowStructureExamplesSeeder` — a catalog covering direct directives
    (`target: none`), validation with `on_error`, HTTP POST + bearer auth,
    `service` and `event` targets, and a config-less PHP `handler` workflow.
  - `WorkflowExamplesSeeder` — aggregator that seeds all of the above.
  - Demo support classes under `src/Examples/` (`DemoInventoryService`,
    `DemoWorkflowEvent`, `DemoGreetingHandler`) so the service/event/handler
    examples execute out of the box.

### Changed
- **One Workflow Manager.** The classic form-based Workflow Manager
  (`workflows/manage`) has been removed; the **visual builder** is now the sole
  editor, labelled **Workflow Manager** at `admin/workflows/builder` (with its
  JSON tab for raw editing). The `WorkflowController` class and the
  `workflows/manage/addedit` view are gone, the `home` route redirects to the
  builder, and the module-seeding migration removes any leftover
  `workflows/manage` menu row on upgrade. The unused `builder.enabled` config
  flag was dropped.
- **Standardised the client directive shape on the flat envelope** —
  `{ "type": "toast", "message": "…", "level": "success" }` — instead of nesting
  fields under a `payload` key. Matches `WorkflowResponse`, the docs, and the
  interactive builder. The bundled example seeders and admin config presets were
  updated accordingly (the engine still passes directives through verbatim, so
  clients that expected nested payloads would need updating).
- The package **no longer ships pre-registered built-in workflows** — the
  `Workflows` registry is empty by default; register your own handlers, or
  publish the examples (`hashtagcms-workflows:publish-examples`) and register
  those. The former `src/BuiltIn` classes now ship as publishable stubs under
  `stubs/`. Removes demo/fake-data handlers that could be mistaken for
  production logic.
- Admin `routes/web.php` rewritten as explicit, self-owned, named routes
  (`hashtagcms.workflows.manage.*`, `hashtagcms.workflows.logs.*`) instead of a
  copy of core's dynamic reflection dispatcher. The package owns its own routing
  and references its controllers directly — the standard pattern for HashtagCMS
  packages (as in hashtagcms-extended). No core changes are required.
- All package admin views now resolve through core's view loader via the
  `cms_modules`-driven `getViewNames()` resolution, and `htcms_workflows_view()`
  is now a thin wrapper over core's `htcms_admin_view()`.
- `cms_modules` menu rows are seeded by stable `controller_name` slugs with
  dynamically allocated auto-increment ids (no hard-coded module ids), so the
  package no longer risks id collisions with core or other packages.

### Fixed
- Admin menu modules now write their `position` on update as well as insert, so
  re-seeding corrects stale/colliding positions left by earlier installs (the
  Workflows group and its items previously collided at one position, which could
  hide them in the admin menu).
- Admin "HTTP REST Preset" now generates an engine-accurate config: it uses a
  single `target` (with the adapter's native `auth` block) plus
  `on_success` / `on_failure`, instead of a `steps` array the engine never
  executed. The preset also now demonstrates using the response via
  `{{ response.body.* }}`.

## [1.0.0]

### Added
- Test suite (PHPUnit + Orchestra Testbench) covering the variable interpolator,
  workflow response builder, the declarative workflow engine, and the API
  execution controller.
- GitHub Actions CI running the test suite on PHP 8.3 and 8.4.
- `master_site_id` config option (env `HASHTAGCMS_WORKFLOWS_MASTER_SITE_ID`) to
  control the fallback site id used during workflow resolution.
- `expose_error_details` config option (env `HASHTAGCMS_WORKFLOWS_EXPOSE_ERRORS`)
  to control whether raw exception messages are returned by the execution API.
- `LICENSE` file (MIT).

### Changed
- The workflow execution API no longer leaks internal exception messages to the
  client by default. Details are now returned only when `expose_error_details`
  is enabled, defaulting to the application's `app.debug` flag. Failures are also
  reported to the application's exception handler.
- The master-site fallback id is now configurable instead of hard-coded to `1`.
- Tightened Composer constraints: `php` `^8.3`, `hashtagcms/hashtagcms`
  `^2.0.6 || ^3.0`, and `minimum-stability` set to `stable`.
