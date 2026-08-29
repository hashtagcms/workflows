# HashtagCMS Workflows Documentation

Welcome to the official technical documentation for **`hashtagcms/workflows`**, the server-driven workflow and action orchestration engine for **HashtagCMS**.

---

## 📚 Documentation Index

| File | Topic | Description |
|---|---|---|
| [01-architecture-overview.md](01-architecture-overview.md) | **Architecture Overview** | Core architecture, SDUI action pipeline, Context & Directive Response model. |
| [02-installation-and-setup.md](02-installation-and-setup.md) | **Installation & Setup** | Composer requirements, Laravel service provider discovery, migrations, and configs. |
| [03-admin-panel-management.md](03-admin-panel-management.md) | **Admin Panel Management** | How to create, configure, search, and manage workflows in the HashtagCMS Admin UI. |
| [04-creating-custom-workflows.md](04-creating-custom-workflows.md) | **Creating Custom Workflows** | Implementing `WorkflowHandlerInterface`, registering aliases, and building custom action logic. |
| [05-built-in-workflows-catalog.md](05-built-in-workflows-catalog.md) | **Example Workflows & Generators** | `make:workflow`, `publish-examples`, and the AddToCart/ApplyCoupon/QuickReorder/SubmitFeedback example handlers. |
| [06-headless-api-and-mobile-integration.md](06-headless-api-and-mobile-integration.md) | **Headless API & Mobile Integration** | API execution contract, dynamic prefixes, and client-side directive execution (KMP / iOS / Web). |
| [07-audit-logging-and-analytics.md](07-audit-logging-and-analytics.md) | **Audit Logging & Analytics** | Execution monitoring, latency benchmarks, debugging failed runs, and log pruning. |
| [08-example-apply-promo-workflow.md](08-example-apply-promo-workflow.md) | **Example: Apply Promo & Discount** | Integrating with a custom `PromoService`, calculating discounts, and returning cart mutation directives. |
| [09-example-add-to-cart-workflow.md](09-example-add-to-cart-workflow.md) | **Example: Add to Cart** | Handling product inventory checks, variant options, cart badges, and upsell drawer directives. |
| [10-example-appointment-booking-workflow.md](10-example-appointment-booking-workflow.md) | **Example: Salon Appointment Booking** | Multi-step salon booking, stylist slot reservation, conflict validation, and confirmation navigation. |
| [11-example-otp-verification-workflow.md](11-example-otp-verification-workflow.md) | **Example: Phone OTP & Auth** | Rate-limited OTP verification, Sanctum token creation, and auth state transition directives. |
| [12-directive-capability-negotiation.md](12-directive-capability-negotiation.md) | **Directive Capability Negotiation** | The `workflow_directives` manifest, per-client capability resolution, fallback/degradation, and telemetry. |
| [13-interactive-workflow-manager.md](13-interactive-workflow-manager.md) | **Interactive Workflow Manager** | The Vue-based visual builder — panels, live preview, cURL, the JSON escape hatch, and how it's built. |

---

## 🚀 Quick Example

```php
use HashtagCms\Workflows\Facades\Workflows;

// Execute any registered workflow on the server
$response = Workflows::execute(
    alias: 'WORKFLOW_APPLY_COUPON',
    payload: ['code' => 'DOM50'],
    siteId: 1,
    platform: 'android'
);

return response()->json($response->toArray());
```

```json
{
  "success": true,
  "message": null,
  "directives": [
    {
      "type": "mutate_cart",
      "action": "apply_coupon",
      "couponCode": "DOM50",
      "discountPercent": 50,
      "maxDiscount": 100,
      "label": "50% OFF (up to ₹100)"
    },
    {
      "type": "toast",
      "message": "🎉 Coupon 'DOM50' applied! 50% OFF (up to ₹100)",
      "level": "success"
    },
    {
      "type": "haptic",
      "intensity": "success"
    }
  ],
  "data": {
    "coupon": {
      "discountPercent": 50,
      "maxDiscount": 100,
      "label": "50% OFF (up to ₹100)"
    }
  }
}
```
