# 01 - Architecture Overview

[📚 Docs Index](README.md) | [Next: Installation & Setup →](02-installation-and-setup.md)

---

## Design Philosophy

The **HashtagCMS Workflows** package decouples frontend mobile/web client actions from rigid client-side code. Instead of hardcoding business rules (like cart logic, discount eligibility, loyalty awards, or dynamic navigation paths) inside mobile apps or frontend bundles, **Workflows** turns every user action into a Server-Driven Workflow.

```
+--------------------------+       POST /api/hashtagcms/public/workflows/v1/execute       +---------------------------+
|                          | ----------------------------------------------------------> |                           |
| Mobile App / Web Client  |                                                             | HashtagCMS Workflows Core |
| (Compose / Swift / Web)  | <---------------------------------------------------------- |                           |
+--------------------------+       JSON Client Directives (toast, mutate, sheet, etc.)  +---------------------------+
```

---

## Core Components

### 1. `WorkflowModuleRegistry`
Serves as the single source of truth for the HashtagCMS Admin UI module system. Defines module IDs, controller bindings, icons, and permissions:
- ID `60`: **Workflows** (Sidebar parent menu)
- ID `61`: **Workflow Manager** (`workflows/builder`) — the visual builder
- ID `62`: **Workflow Logs** (`workflows/logs`)

### 2. `WorkflowContext`
Encapsulates all contextual parameters passed with an action:
- `workflow`: The `Workflow` Eloquent model instance
- `payload`: Dynamic input dictionary provided by the client
- `siteId`: The current HashtagCMS site context
- `platform`: Target client platform (`android`, `ios`, `web`)
- `user`: Currently authenticated user (when auth is required)

### 3. `WorkflowResponse`
A fluent builder that compiles response metadata and an ordered list of **Client Directives**:
```php
$response = (new WorkflowResponse())
    ->setSuccess(true)
    ->setMessage('Cart updated successfully')
    ->addToast('Item added to your basket', 'success')
    ->addDirective([
        'type' => 'mutate_cart',
        'action' => 'increment',
        'item_id' => 101
    ]);
```

### 4. `WorkflowHandlerInterface`
The standardized contract that all workflow handlers must implement:
```php
namespace HashtagCms\Workflows\Contracts;

use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

interface WorkflowHandlerInterface
{
    public function handle(WorkflowContext $context): WorkflowResponse;
}
```

### 5. `DirectiveNegotiator`
Between the response and the client sits a **capability-negotiation** step. It
rewrites the emitted directive list against a per-site, per-platform manifest
(the `workflow_directives` table) so a client only ever receives directives it
can render — downgrading unsupported directives to a fallback, or dropping them,
and recording what changed. Fail-safe: unknown types and an empty manifest pass
straight through. See [12 - Directive Capability Negotiation](12-directive-capability-negotiation.md).

### 6. `WorkflowLog`
Every workflow invocation automatically records:
- Execution latency in milliseconds (`execution_time_ms`)
- Input payload and sanitized parameters
- Output directives sent to the client
- The negotiated client (`client_platform`, `client_app_version`) and any downgraded/dropped directives
- Error messages and stack traces (on failure)

---

[📚 Docs Index](README.md) | [Next: Installation & Setup →](02-installation-and-setup.md)
