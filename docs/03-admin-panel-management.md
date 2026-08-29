# 03 - Admin Panel Management

[← Previous: Installation & Setup](02-installation-and-setup.md) | [📚 Docs Index](README.md) | [Next: Creating Custom Workflows →](04-creating-custom-workflows.md)

---

HashtagCMS Workflows provides native administration screens within the **HashtagCMS Admin Panel**.

---

## 1. Accessing Workflows in Admin

Once installed and migrated, you will find the **Workflows** category in the left sidebar menu, with four modules:

1. **Workflow Manager** (`admin/workflows/builder`) — create and edit workflows with the Vue-based visual builder (with a raw-JSON tab). See [13 - Interactive Workflow Manager](13-interactive-workflow-manager.md).
2. **Workflow Directives** (`admin/workflows/directives`) — manage the directive capability manifest. See [12 - Directive Capability Negotiation](12-directive-capability-negotiation.md).
3. **Workflow Logs** (`admin/workflows/logs`) — execution audit trail.
4. **Workflow Playground** (`admin/workflows/playground`) — run published workflows and watch directives render.

---

## 2. Managing Workflows (`workflows/builder`)

The **Workflow Manager** is the visual builder (full detail in
[13 - Interactive Workflow Manager](13-interactive-workflow-manager.md)). It
provides full CRUD:
- **Listing View**: View all workflows, their aliases, publish status, and last update.
- **Search & Filter**: Search workflows by alias or name.
- **Add / Edit**: Compose the workflow visually — identity (name, alias, description, optional PHP handler class, **Authentication Required**, **Publish Status**), validation rules, a target, and the client directives — or flip to the **JSON** tab for the raw `config`. A **Live preview** runs the current config through the engine before you save.

---

## 3. Workflow Logs & Execution Auditing (`workflows/logs`)

The Workflow Logs module tracks every execution:
- **Live Latency**: See millisecond-level execution time (`execution_time_ms`)
- **Status Indicator**: Visual green (SUCCESS) or red (FAILED) badges
- **Payload Inspection**: View the exact JSON parameters supplied by the client
- **Directive Inspection**: View the exact directives emitted back to the client application
- **Negotiation Telemetry**: The client the run was negotiated for (`client_platform`, `client_app_version`) and any directives that were downgraded or dropped — see [07 - Audit Logging & Analytics](07-audit-logging-and-analytics.md).

---

## 4. Workflow Directives (`workflows/directives`)

Manage the **directive capability manifest** — the catalogue of client directive
types (72 shipped by default), each with its per-platform minimum version,
payload schema, and fallback. This is what powers capability negotiation and the
directive picker in the Interactive Workflow Manager. Full detail in
[12 - Directive Capability Negotiation](12-directive-capability-negotiation.md).

---

## 5. Workflow Playground (`workflows/playground`)

A read-only demo screen that lists every published workflow, lets you edit a
sample payload and **Run** it against the execute endpoint, and renders both the
returned directives and the raw request/response JSON — the quickest way to see
the server-driven model in action after install.

---

[← Previous: Installation & Setup](02-installation-and-setup.md) | [📚 Docs Index](README.md) | [Next: Creating Custom Workflows →](04-creating-custom-workflows.md)
