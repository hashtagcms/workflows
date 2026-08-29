# 09 - Example: Add To Cart Workflow

[← Previous: Example: Apply Promo Workflow](08-example-apply-promo-workflow.md) | [📚 Docs Index](README.md) | [Next: Example: Appointment Booking Workflow →](10-example-appointment-booking-workflow.md)

---

This example demonstrates how to implement an **Add To Cart** workflow that validates inventory/stock, updates the user's active cart in the database, and emits client directives including cart counter increments, success toasts, and optional upsell recommendations.

---

## 🎯 Business Scenario

1. A customer taps **"Add to Cart"** on a luxury hair mask product ($45.00).
2. The server must:
   - Verify product existence and inventory availability.
   - Attach selected variant options (e.g., Size: 250ml).
   - Update or create the active cart session in the database.
   - Return directives to increment the cart counter badge, display a confirmation toast, and optionally trigger a recommendation drawer.

---

## 💻 1. The Cart Service (`CartService.php`)

```php
namespace App\Services;

class CartService
{
    public function addItem(int $productId, int $quantity = 1, array $options = [], ?int $userId = null): array
    {
        $product = \App\Models\Product::find($productId);

        if (!$product || !$product->is_active) {
            return ['success' => false, 'message' => 'Product is currently unavailable.'];
        }

        if ($product->stock_quantity < $quantity) {
            return ['success' => false, 'message' => "Only {$product->stock_quantity} items remaining in stock."];
        }

        // Add to user/session cart table
        $cartItem = \App\Models\CartItem::updateOrCreate([
            'user_id' => $userId,
            'product_id' => $productId,
        ], [
            'quantity' => \DB::raw("quantity + {$quantity}"),
            'unit_price' => $product->price,
            'options' => json_encode($options)
        ]);

        $newCartCount = \App\Models\CartItem::where('user_id', $userId)->sum('quantity');

        return [
            'success' => true,
            'productName' => $product->name,
            'unitPrice' => (float)$product->price,
            'quantity' => $quantity,
            'cartCount' => (int)$newCartCount,
            'upsellProduct' => $product->recommended_upsell_id ?? null
        ];
    }
}
```

---

## 🛠️ 2. The Workflow Handler (`AddToCartWorkflow.php`)

```php
namespace App\Workflows;

use App\Services\CartService;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

class AddToCartWorkflow implements WorkflowHandlerInterface
{
    public function __construct(protected CartService $cartService) {}

    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $productId = (int)$context->get('productId');
        $quantity = (int)$context->get('quantity', 1);
        $options = (array)$context->get('options', []);
        $userId = $context->getUser()?->id;

        // 1. Delegate to CartService
        $result = $this->cartService->addItem($productId, $quantity, $options, $userId);

        if (!$result['success']) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast($result['message'], 'error')
                ->haptic('error');
        }

        // 2. Build Response Builder with Directives
        $response = WorkflowResponse::make()
            ->setSuccess(true)
            ->toast("✨ Added '{$result['productName']}' to your cart!", 'success')
            ->directive('mutate_cart', [
                'action' => 'add_item',
                'productId' => $productId,
                'quantity' => $quantity,
                'totalCartItems' => $result['cartCount']
            ])
            ->haptic('success');

        // 3. Optional Dynamic Upsell Directive
        if (!empty($result['upsellProduct'])) {
            $response->directive('open_sheet', [
                'sheetType' => 'product_upsell',
                'productId' => $result['upsellProduct'],
                'headline' => 'Frequently Purchased Together'
            ]);
        }

        return $response;
    }
}
```

---

## 🌐 3. HTTP API Execution

### Request
```http
POST /api/hashtagcms/public/workflows/v1/execute HTTP/1.1
Host: your-cms-domain.com
Content-Type: application/json

{
    "workflow": "WORKFLOW_ADD_TO_CART",
    "payload": {
        "productId": 104,
        "quantity": 1,
        "options": {
            "size": "250ml",
            "scent": "Cedarwood & Rose"
        }
    },
    "site_id": 1,
    "platform": "ios"
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
            "message": "✨ Added 'Botanical Scalp Mask' to your cart!",
            "level": "success"
        },
        {
            "type": "mutate_cart",
            "action": "add_item",
            "productId": 104,
            "quantity": 1,
            "totalCartItems": 3
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

[← Previous: Example: Apply Promo Workflow](08-example-apply-promo-workflow.md) | [📚 Docs Index](README.md) | [Next: Example: Appointment Booking Workflow →](10-example-appointment-booking-workflow.md)
