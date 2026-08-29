# 05 - Example Workflows & Generators

[← Previous: Creating Custom Workflows](04-creating-custom-workflows.md) | [📚 Docs Index](README.md) | [Next: Headless API & Mobile Integration →](06-headless-api-and-mobile-integration.md)

---

The package ships **no pre-registered PHP handlers** — your application owns its
workflow logic. Two artisan commands help you get started:

```bash
# Scaffold a fresh, empty handler in app/Workflows
php artisan make:workflow ApplyCoupon --alias=WORKFLOW_APPLY_COUPON

# Publish the example commerce handlers into app/Workflows/Examples
php artisan hashtagcms-workflows:publish-examples
```

Both commands print the line to register the handler. Register it in a service
provider (e.g. `AppServiceProvider::boot`):

```php
use HashtagCms\Workflows\Facades\Workflows;

Workflows::register('WORKFLOW_APPLY_COUPON', \App\Workflows\Examples\ApplyCouponWorkflow::class);
```

The four handlers below are the ones `publish-examples` writes — they are
**starting points with demo data**, meant to be edited against your own
services, not shipped as-is.

---

## 1. `WORKFLOW_ADD_TO_CART`
* **Handler**: `App\Workflows\Examples\AddToCartWorkflow`
* **Purpose**: Adds an item to the cart. If the product needs customization it instead opens the product customizer sheet.
* **Payload**:
  ```json
  {
    "productId": 101,
    "title": "Farmhouse Pizza",
    "price": 399,
    "imageUrl": "https://…/pizza.jpg",
    "needsCustomization": false
  }
  ```
* **Emitted Directives** (standard add):
  1. `mutate_cart` (`action: add`, `productId`, `title`, `price`, `imageUrl`, `quantity: 1`)
  2. `toast` (level: `success`, message: `"Added Farmhouse Pizza to your cart!"`)
  3. `haptic` (intensity: `success`)
* When `needsCustomization` is `true`: a single `open_sheet` (`product_customizer`) directive is returned instead.

---

## 2. `WORKFLOW_APPLY_COUPON`
* **Handler**: `App\Workflows\Examples\ApplyCouponWorkflow`
* **Purpose**: Validates coupon codes server-side and returns the discount as cart-mutation directives.
* **Supported Codes**:
  - `DOM50`: 50% off, up to 100 max.
  - `OFFER80`: 40% off, up to 80 max.
* **Payload**:
  ```json
  {
    "code": "DOM50"
  }
  ```
* **Emitted Directives** (valid code):
  1. `mutate_cart` (`action: apply_coupon`, `couponCode`, `discountPercent`, `maxDiscount`, `label`)
  2. `toast` (level: `success`, message: `"Coupon 'DOM50' applied! 50% OFF (up to 100)"`)
  3. `haptic` (intensity: `success`)
* On an invalid/expired code: `success: false`, an error `toast`, and a `haptic` (intensity: `error`).

---

## 3. `WORKFLOW_QUICK_REORDER`
* **Handler**: `App\Workflows\Examples\QuickReorderWorkflow`
* **Purpose**: One-click repeat order — rebuilds the cart from a previous purchase.
* **Payload**:
  ```json
  {
    "orderId": "ORD-9824",
    "title": "Farmhouse Pizza",
    "price": 399
  }
  ```
* **Emitted Directives**:
  1. `mutate_cart` (`action: add`, `productId` (from `orderId`), `title`, `price`, `quantity: 1`)
  2. `toast` (level: `success`, message: `"Reordered Farmhouse Pizza! Cart ready for checkout."`)
  3. `haptic` (intensity: `success`)

---

## 4. `WORKFLOW_SUBMIT_FEEDBACK`
* **Handler**: `App\Workflows\Examples\SubmitFeedbackWorkflow`
* **Purpose**: Records a satisfaction rating and grants loyalty points (`50` for a rating ≥ 4, otherwise `20`).
* **Payload**:
  ```json
  {
    "rating": 5
  }
  ```
* **Emitted Directives**:
  1. `toast` (level: `success`, message: `"⭐ Thank you! You earned +50 Cheesy Rewards points 🧀"`)
  2. `haptic` (intensity: `success`)
* **Response data**: `{ "pointsAdded": 50 }`.

---

[← Previous: Creating Custom Workflows](04-creating-custom-workflows.md) | [📚 Docs Index](README.md) | [Next: Headless API & Mobile Integration →](06-headless-api-and-mobile-integration.md)
