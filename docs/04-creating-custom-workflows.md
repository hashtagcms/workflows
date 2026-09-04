# 04 - Creating Custom Workflows

[← Previous: Admin Panel Management](03-admin-panel-management.md) | [📚 Docs Index](README.md) | [Next: Example Workflows & Generators →](05-built-in-workflows-catalog.md)

---

HashtagCMS Workflows supports two powerful approaches for creating custom workflows:
1. **Generic Config-Driven Workflows (Zero-PHP / Declarative JSON)**: Define input validation, target HTTP/service endpoints, and client directives purely in JSON.
2. **Custom PHP Class Handlers**: Write specialized PHP classes implementing `WorkflowHandlerInterface` for complex proprietary business algorithms.

> **Tip:** scaffold a PHP handler with `php artisan make:workflow MyWorkflow`
> (see [Example Workflows & Generators](05-built-in-workflows-catalog.md)). The
> package ships no pre-registered handlers — you register your own.

---

## Approach 1: Generic Config-Driven Workflows (Recommended)

Organizations can connect client actions to their own backend microservices, ERP, Shopify, or internal services without writing PHP code.

### 1. JSON Configuration Schema Structure

```json
{
  "validation": {
    "rules": {
      "product_id": "required|integer",
      "quantity": "required|integer|min:1"
    },
    "messages": {
      "product_id.required": "Product selection is required."
    }
  },
  "target": {
    "type": "http_request",
    "http": {
      "method": "POST",
      "url": "{{ env.INVENTORY_SERVICE_URL | default: 'https://api.mycompany.com' }}/v1/cart/items",
      "headers": {
        "Authorization": "Bearer {{ env.INVENTORY_API_KEY }}",
        "Content-Type": "application/json",
        "X-Site-Id": "{{ site.id }}"
      },
      "body": {
        "sku": "{{ payload.product_id }}",
        "qty": "{{ payload.quantity }}",
        "customer_id": "{{ user.id | default: 'guest' }}"
      },
      "timeout": 5
    }
  },
  "on_success": {
    "message": "Added item #{{ payload.product_id }} to basket",
    "directives": [
      {
        "type": "mutate_cart",
        "action": "add",
        "item_id": "{{ payload.product_id }}",
        "quantity": "{{ payload.quantity }}",
        "cart_total": "{{ response.body.new_cart_total }}"
      },
      {
        "type": "toast",
        "message": "{{ response.body.message | default: 'Item added successfully!' }}",
        "level": "success"
      },
      {
        "type": "haptic",
        "intensity": "medium"
      }
    ]
  },
  "on_failure": {
    "directives": [
      {
        "type": "toast",
        "message": "{{ error.message | default: 'Could not add item to basket' }}",
        "level": "error"
      },
      {
        "type": "haptic",
        "intensity": "error"
      }
    ]
  }
}
```

> **Requiring authentication.** The JSON above is the workflow's `config` column.
> The workflow *row* also has an `auth_required` boolean: set it to `true` and the
> engine refuses to run the workflow (returns **401**) unless a valid identity was
> resolved for the caller — via the local guard, or a token validated by an SSO
> provider. It does not change the config; it is the on/off switch. See
> [SSO & External Login](14-sso-and-external-login.md) for exactly which request
> the engine makes to validate a token.

### 2. Available Target Adapters

| Target Type | Purpose | Configuration Keys |
|---|---|---|
| `http_request` / `http` | Forward action to external REST API | `method`, `url`, `headers`, `body`, `query`, `auth`, `timeout` |
| `service_call` / `service` | Invoke internal Laravel service | `class`, `method`, `arguments` |
| `event_dispatch` / `event` | Fire Laravel Event or push to Queue | `class`, `payload` |
| `none` / `direct` | Emit client directives directly | Direct response without external calls |

### 3. Template Variable Interpolation Reference

| Placeholder | Resolves To |
|---|---|
| `{{ payload.key }}` | Client input parameter (e.g. `product_id`, `qty`, `coupon_code`) |
| `{{ user.id }}` | Currently authenticated user's ID (or null for guests) |
| `{{ user.email }}` | Authenticated user's email address |
| `{{ claims.key }}` | A normalized identity **claim** from the resolved caller (e.g. `{{ claims.email }}`, `{{ claims.roles }}`). Claims are the standard (JWT/OAuth) way to carry identity attributes; they are populated from the resolved `WorkflowIdentity` — chiefly by an SSO provider's `identity.claims` mapping when login is handled by another service. Empty when nothing set them. See [SSO & External Login](14-sso-and-external-login.md). |
| `{{ identity.raw.key }}` | Opt-in **raw** passthrough of the token-validator's response (e.g. `{{ identity.raw.profile.mobile }}`), populated only by a provider's `identity.raw` mapping. Convenient, but couples the workflow to the provider's response shape — prefer curated `claims`. `{{ identity.* }}` also exposes `user_id`, `external_user_id`, `provider`. |
| `{{ site.id }}` | Current HashtagCMS site context ID |
| `{{ env.VAR_NAME }}` | Environment variable (e.g. API keys, external URLs) |
| `{{ response.body.key }}` | Data returned from the target service/HTTP response |
| `{{ response.status }}` | HTTP status code returned (e.g. 200, 201, 404) |
| `{{ variable \| default: 'val' }}` | Fallback value if the variable is null or missing |

### 4. Returning `data`

Directives tell the *client* what to do; if you also want to return raw **data**
in the response's `data` field (for the client to read directly), add an optional
`data` object to `on_success` (or `on_failure`). It is interpolated like
everything else, so it's the natural way to surface a target's response:

```json
"on_success": {
  "message": "Loaded.",
  "data": {
    "items": "{{ response.body }}",
    "page": "{{ payload.page | default: 1 }}"
  },
  "directives": [ { "type": "toast", "message": "Done", "level": "success" } ]
}
```

The execute response then carries `"data": { "items": [ … ], "page": 1 }`.

---

## Approach 2: Custom PHP Class Handlers

For complex mathematical algorithms or proprietary business logic, create a class implementing `HashtagCms\Workflows\Contracts\WorkflowHandlerInterface`:

```php
namespace App\Workflows;

use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

class CustomLoyaltyRedemptionWorkflow implements WorkflowHandlerInterface
{
    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $payload = $context->getPayload();
        $points = (int)($payload['points'] ?? 0);

        if ($points <= 0) {
            return (new WorkflowResponse())
                ->setSuccess(false)
                ->setMessage('Invalid points amount specified.')
                ->addToast('Please enter a valid points amount.', 'error');
        }

        $discountAmount = ($points / 100) * 5.0;

        return (new WorkflowResponse())
            ->setSuccess(true)
            ->setMessage("Redeemed {$points} loyalty points!")
            ->addToast("Redeemed {$points} points! Saved \${$discountAmount}", 'success')
            ->addDirective([
                'type' => 'mutate_cart',
                'discount_amount' => $discountAmount,
                'points_deducted' => $points,
            ]);
    }
}
```

---

## Step 3: Triggering Workflows from SDUI / Client

In your HashtagCMS category or module JSON:

```json
{
  "viewType": "ACTION_BUTTON",
  "data": {
    "title": "Add to Basket",
    "action": {
      "type": "workflow",
      "workflow": "WORKFLOW_DYNAMIC_CART",
      "payload": {
        "product_id": 101,
        "quantity": 2
      }
    }
  }
}
```

---

[← Previous: Admin Panel Management](03-admin-panel-management.md) | [📚 Docs Index](README.md) | [Next: Example Workflows & Generators →](05-built-in-workflows-catalog.md)
