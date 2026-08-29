# 13 - Interactive Workflow Manager

[← Previous: Directive Capability Negotiation](12-directive-capability-negotiation.md) | [📚 Docs Index](README.md)

---

The **Workflow Manager** (`admin/workflows/builder`, menu label "Workflow
Manager") is the Vue-based visual editor for workflows — it lets you compose a
workflow (validation, a target, and client directives) visually instead of
hand-writing the JSON `config`, with a **JSON tab** always available for raw
editing. It is the single workflow editor in the package; there is no separate
classic form.

---

## Layout

**Identity** (always visible, above the view toggle): name, alias, description,
the optional **custom PHP handler** class, and the **Published** / **Requires
login (Sanctum)** toggles — the same top-level `workflows` columns the classic
editor exposes.

**Visual builder** (the default view):

- **Validation** — add `field → rules` rows (e.g. `code → required|string`).
  Rows commit on blur. Preserves any `messages` / `on_error` block the visual
  builder doesn't model, and normalises top-level `rules` into `validation.rules`.
- **Target** — pick `None` / `HTTP` / `Service` / `Event`; the relevant fields
  appear (method, URL, headers, query, bearer auth, body — or class/method/args,
  or event class/payload).
- **On success / On failure** — build the client directives for each branch:
  - a compact **grouped dropdown** to add a directive (fed live from the
    [directive manifest](12-directive-capability-negotiation.md) via
    `GET …/workflows/v1/directives`);
  - each directive is a **card** whose fields are generated from that directive's
    `schema`, with human labels, help text, and example placeholders;
  - a **+ value** helper inserts interpolation tokens (`{{ payload.x }}`,
    `{{ response.body.x }}`, …) — response tokens are hidden when there's no target;
  - **drag the ⠿ handle to reorder**, single-click the caret (or double-click the
    header) to **collapse/expand** a card.

**Visual ⇄ JSON toggle** — flip to a raw JSON editor at any time; the two stay in
sync, so nothing the visual builder doesn't model is ever lost.

---

## Live preview

A dark **Live preview** panel runs the current **unsaved** config through the
engine plus capability negotiation (no persistence) and renders the returned
directives, exactly as the real execute path would.

- The **payload auto-fills** from the workflow's inputs — the validation rule
  names plus any `{{ payload.x }}` referenced in the config — with sample values
  inferred from the rules. `↻ from fields` resets it to exactly those fields.
- Set a **platform** / **app version** to see negotiation in action (e.g. a
  native-only `haptic` dropped on `web`).
- **Get cURL for this workflow** produces a ready-to-run `curl` for the public
  execute endpoint, using the current payload and client — with a copy button.

---

## Safety nets

- **`?viewType=json`** — append it to the edit URL
  (`admin/workflows/builder/edit/{id}?viewType=json`) to open straight into the
  raw JSON editor, in case the visual builder ever chokes on an unusual config.
- The **JSON tab** is a lossless editor for anything the visual panels don't
  model — you're never blocked by the visual layer.

---

## How it's built (for maintainers)

- Source: Vue 3 single-file components under `resources/js/` (`App.vue` +
  `components/` + `lib/`), bundled with **webpack** (not Vite).
- The compiled bundle ships in `resources/dist/workflow-builder.{js,css}` and is
  served from a whitelisted package route — no `vendor:publish` needed.
- The admin blade (`resources/views/workflows/builder/index.blade.php`) mounts
  the app as a self-contained island: it lives in a `v-pre` block (so the admin's
  own Vue app leaves it alone) and receives its initial data via a base64
  `data-init` attribute (the admin Vue strips `<script>` tags).
- Backend: `WorkflowBuilderController` (extends the classic controller) — `store()`
  returns JSON for the AJAX save, `preview()` runs a transient config through
  `GenericWorkflowEngine` + `DirectiveNegotiator`.

To modify the UI and rebuild:

```bash
npm install
npm run build     # production bundle
npm run dev       # watch mode
```

---

[← Previous: Directive Capability Negotiation](12-directive-capability-negotiation.md) | [📚 Docs Index](README.md)
