# 03 - Admin Panel Management

[← Previous: Installation & Setup](02-installation-and-setup.md) | [📚 Docs Index](README.md) | [Next: Creating Custom Workflows →](04-creating-custom-workflows.md)

---

HashtagCMS Workflows provides native administration screens within the **HashtagCMS Admin Panel**.

---

## 1. Accessing Workflows in Admin

Once installed and migrated, you will find the **Workflows** category in the left sidebar menu, with these modules:

1. **Workflow Manager** (`admin/workflows/builder`) — create and edit workflows with the Vue-based visual builder (with a raw-JSON tab). See [13 - Interactive Workflow Manager](13-interactive-workflow-manager.md).
2. **Workflow Directives** (`admin/workflows/directives`) — manage the directive capability manifest. See [12 - Directive Capability Negotiation](12-directive-capability-negotiation.md).
3. **Workflow Logs** (`admin/workflows/logs`) — execution audit trail.
4. **SSO Providers** (`admin/workflows/sso`) — external-login providers that resolve workflow identity. See [14 - SSO & External Login](14-sso-and-external-login.md).

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

## 5. SSO Providers (`workflows/sso`)

Manage external-login / SSO providers that verify a client credential and resolve
it to a workflow identity — no code required. Full detail in
[14 - SSO & External Login](14-sso-and-external-login.md).

---

[← Previous: Installation & Setup](02-installation-and-setup.md) | [📚 Docs Index](README.md) | [Next: Creating Custom Workflows →](04-creating-custom-workflows.md)
