# 07 - Audit Logging & Analytics

[← Previous: Headless API & Mobile Integration](06-headless-api-and-mobile-integration.md) | [📚 Docs Index](README.md) | [Next: Example: Apply Promo Workflow →](08-example-apply-promo-workflow.md)

---

## 1. Automated Execution Logging

Every workflow execution is automatically intercepted and audited in the `workflow_logs` table.

```
+----+-----------------------+-----------+-------------------+---------------------+
| ID | Workflow Alias        | Is Success| Execution Time    | Created At          |
+----+-----------------------+-----------+-------------------+---------------------+
| 1  | WORKFLOW_QUICK_REORDER| 1 (true)  | 2 ms              | 2026-08-21 16:44:00 |
| 2  | WORKFLOW_APPLY_COUPON | 1 (true)  | 4 ms              | 2026-08-21 16:44:30 |
| 3  | WORKFLOW_ADD_TO_CART  | 1 (true)  | 1 ms              | 2026-08-21 16:45:10 |
+----+-----------------------+-----------+-------------------+---------------------+
```

---

## 2. Inspecting Log Details in Admin

Navigate to **Admin $\rightarrow$ Workflows $\rightarrow$ Workflow Logs** ([`/admin/workflows/logs`](http://your-domain/admin/workflows/logs)). Click **View Log Detail** on any row to inspect:
- Millisecond latency
- Session and site context
- Exact client input JSON payload
- Exact server emitted directives JSON

---

## 2a. Capability-Negotiation Telemetry

When [directive capability negotiation](12-directive-capability-negotiation.md)
is enabled, each log row also records which client the run was negotiated for and
what negotiation changed:

| Column | Meaning |
|---|---|
| `client_platform` | The platform the request was negotiated for (`web` / `android` / `ios`). |
| `client_app_version` | The client app version used for version-based directive resolution. |
| `negotiation` | JSON of what changed: `directives_downgraded` (`{from,to}` pairs) and `directives_dropped` — `null` when nothing was changed. |

This makes "which client versions can't render directive *X*, and how often" a
`workflow_logs` query rather than a guess — the primary signal for deciding when
an old directive is safe to require, or when a client rollout has caught up.

---

## 2b. Caller Identity

Each row records who executed the workflow. Identity is resolved through the
`WorkflowIdentityResolver` (by default the local Laravel guard), so these columns
are populated the same way whether login is local or handled by an external
service.

| Column | Meaning |
|---|---|
| `user_id` | The **local** integer user id (Laravel guard / local users table). `null` for external subjects and anonymous callers. |
| `external_user_id` | The external subject reference (e.g. an SSO/UUID `sub`) when login is handled by another service and there is no local user row. `null` for local users. |
| `sso_provider_alias` | The SSO provider that resolved the identity, or `null` for local / explicitly-supplied identities. |

A local login populates `user_id`; an external login populates `external_user_id`
+ `sso_provider_alias`; an anonymous run leaves all three `null`.

---

## 3. Log Pruning and Maintenance

You can configure automatic retention rules in `config/hashtagcms-workflows.php`:

```php
'logging' => [
    'enabled' => true,
    'prune_days' => 30, // Retain logs for 30 days
],
```

Or prune logs on demand via Artisan / Tinker:

```php
use HashtagCms\Workflows\Models\WorkflowLog;

// Delete logs older than 30 days
WorkflowLog::where('created_at', '<', now()->subDays(30))->delete();
```

---

[← Previous: Headless API & Mobile Integration](06-headless-api-and-mobile-integration.md) | [📚 Docs Index](README.md) | [Next: Example: Apply Promo Workflow →](08-example-apply-promo-workflow.md)
