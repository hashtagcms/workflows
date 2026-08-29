# 08 - Example: Apply Promo & Coupon Workflow

[← Previous: Audit Logging & Analytics](07-audit-logging-and-analytics.md) | [📚 Docs Index](README.md) | [Next: Example: Add to Cart Workflow →](09-example-add-to-cart-workflow.md)

---

This real-world example demonstrates how to integrate an existing backend **Promo / Discount Service** with the HashtagCMS Workflow Engine to validate coupons and emit real-time cart mutations and toast directives to mobile and web clients.

---

## 🎯 Business Scenario

1. A client enters a coupon code (e.g., `SUMMER20`) on the checkout screen and taps **"Apply"**.
2. The server must:
   - Validate that the coupon code exists and is currently active.
   - Check if the cart meets minimum order thresholds.
   - Calculate the discount amount.
   - Emits structured client directives (`mutate_cart`, `toast`, `haptic`).

---

## 🔄 Sequence Diagram

```
+----------------+      1. POST execute (code, cartTotal)      +-----------------------------+
|                | ------------------------------------------> |                             |
|  Mobile Client |                                             | HashtagCMS Workflows Engine |
|                | <------------------------------------------ |                             |
+----------------+      4. Directives (mutate_cart, toast)     +-----------------------------+
                                                                             |
                                                      2. applyCoupon()       | 3. [discount, finalTotal]
                                                                             v
                                                               +-----------------------------+
                                                               |     App\Services\PromoService|
                                                               +-----------------------------+
```

---

## 💻 1. The Business Service (`PromoService.php`)

```php
namespace App\Services;

class PromoService
{
    /**
     * Calculate coupon discount against current cart.
     */
    public function applyCoupon(string $code, float $cartTotal, ?int $userId = null): array
    {
        $code = strtoupper(trim($code));

        // 1. Fetch coupon record from database
        $coupon = \App\Models\Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->is_active) {
            return [
                'success' => false,
                'message' => "Promo code '{$code}' is invalid or expired."
            ];
        }

        if ($cartTotal < $coupon->min_spend) {
            return [
                'success' => false,
                'message' => "Minimum order of \${$coupon->min_spend} required for this promo."
            ];
        }

        // 2. Calculate percentage or fixed discount
        $discountAmount = ($coupon->type === 'percent')
            ? round(($cartTotal * $coupon->value) / 100, 2)
            : min($coupon->value, $cartTotal);

        if ($coupon->max_discount && $discountAmount > $coupon->max_discount) {
            $discountAmount = (float)$coupon->max_discount;
        }

        $finalTotal = max(0, $cartTotal - $discountAmount);

        return [
            'success' => true,
            'discountAmount' => $discountAmount,
            'finalTotal' => $finalTotal,
            'couponCode' => $code,
            'label' => $coupon->label ?? "{$coupon->value}% OFF"
        ];
    }
}
```

---

## 🛠️ 2. The Workflow Handler (`ApplyPromoWorkflow.php`)

```php
namespace App\Workflows;

use App\Services\PromoService;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

class ApplyPromoWorkflow implements WorkflowHandlerInterface
{
    public function __construct(protected PromoService $promoService) {}

    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $code = (string)$context->get('code');
        $cartTotal = (float)$context->get('cartTotal', 0.0);
        $user = $context->getUser();

        // 1. Validate input presence
        if (empty($code)) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast('Please enter a valid coupon code.', 'error')
                ->haptic('error');
        }

        // 2. Delegate calculation to PromoService
        $result = $this->promoService->applyCoupon($code, $cartTotal, $user?->id);

        // 3. Handle Failure Branch
        if (!$result['success']) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast($result['message'], 'error')
                ->haptic('error');
        }

        // 4. Handle Success Branch with Directives
        return WorkflowResponse::make()
            ->setSuccess(true)
            ->toast("🎉 Promo '{$code}' applied! You saved \${$result['discountAmount']}", 'success')
            ->directive('mutate_cart', [
                'action' => 'apply_discount',
                'couponCode' => $result['couponCode'],
                'discount' => $result['discountAmount'],
                'finalTotal' => $result['finalTotal'],
                'label' => $result['label']
            ])
            ->haptic('success');
    }
}
```

---

## 📄 3. Alternative: Declarative JSON Configuration

If you prefer configuring the promo workflow without writing a PHP class, paste this JSON schema into the **Workflow Manager**'s JSON tab (`/admin/workflows/builder/edit/{id}`):

```json
{
    "version": "1.0",
    "rules": {
        "code": "required|string|max:32",
        "cartTotal": "required|numeric|min:0.01"
    },
    "directives": [
        {
            "type": "mutate_cart",
            "action": "apply_discount",
            "couponCode": "{{ payload.code }}",
            "discountPercent": 15
        },
        {
            "type": "toast",
            "message": "Promo code {{ payload.code }} applied successfully!",
            "level": "success"
        },
        {
            "type": "haptic",
            "intensity": "success"
        }
    ]
}
```

---

## 🌐 4. HTTP API Interaction

### Request
```http
POST /api/hashtagcms/public/workflows/v1/execute HTTP/1.1
Host: your-cms-domain.com
Content-Type: application/json

{
    "workflow": "WORKFLOW_APPLY_PROMO",
    "payload": {
        "code": "SUMMER20",
        "cartTotal": 150.00
    },
    "site_id": 1,
    "platform": "android"
}
```

### Success Response
```json
{
    "success": true,
    "message": null,
    "directives": [
        {
            "type": "toast",
            "message": "🎉 Promo 'SUMMER20' applied! You saved $30.00",
            "level": "success"
        },
        {
            "type": "mutate_cart",
            "action": "apply_discount",
            "couponCode": "SUMMER20",
            "discount": 30.0,
            "finalTotal": 120.0,
            "label": "20% OFF"
        },
        {
            "type": "haptic",
            "intensity": "success"
        }
    ],
    "data": []
}
```

---

[← Previous: Audit Logging & Analytics](07-audit-logging-and-analytics.md) | [📚 Docs Index](README.md) | [Next: Example: Add to Cart Workflow →](09-example-add-to-cart-workflow.md)
