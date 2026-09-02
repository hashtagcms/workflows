# Changelog

All notable changes to `hashtagcms/workflows` are documented here. This project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
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
  Playground, Directives), the directive manifest, and the bundled example
  workflows — no separate `db:seed` step. Opt out of the demo workflows with
  `install.seed_examples=false` (env `HASHTAGCMS_WORKFLOWS_SEED_EXAMPLES`).

- **Workflow Playground** — a new admin page (`admin/workflows/playground`, under
  the *Workflows* menu) that lists every published workflow, lets you edit a
  payload and **Run** it against the execute endpoint, and shows both the
  rendered directives and the raw request/response JSON.
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
- All package admin views now resolve through core's view loader: manage and
  playground go through the `cms_modules`-driven `getViewNames()` resolution, and
  `htcms_workflows_view()` is now a thin wrapper over core's `htcms_admin_view()`.
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
