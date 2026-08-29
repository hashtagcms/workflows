# 06 - Headless API & Mobile Integration

[← Previous: Example Workflows & Generators](05-built-in-workflows-catalog.md) | [📚 Docs Index](README.md) | [Next: Audit Logging & Analytics →](07-audit-logging-and-analytics.md)

---

## 1. REST Execution Endpoint

HashtagCMS Workflows exposes a headless execution endpoint dynamically mounted under the package's configured API prefix (defaults to `/api/hashtagcms`):

```http
POST /api/hashtagcms/public/workflows/v1/execute HTTP/1.1
Host: your-domain.com
Content-Type: application/json
Accept: application/json

{
  "workflow": "WORKFLOW_APPLY_COUPON",
  "payload": {
    "code": "DOM50"
  },
  "site_id": 1,
  "platform": "android"
}
```

### Response Schema

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

---

## 2. Client-Side Execution Pipeline (Kotlin Multiplatform / Swift / Web)

When the client triggers an action of type `workflow`:
1. Call `executeWorkflow(alias, payload)` on `HashtagCmsClient`.
2. Iterate through the array of `directives` in the response.
3. Dispatch each directive to its respective handler:

### Kotlin Multiplatform Implementation Example

```kotlin
suspend fun executeWorkflow(workflow: String, payload: Map<String, Any>) {
    val response = client.executeWorkflow(
        WorkflowRequestDto(workflow = workflow, payload = payload)
    )

    if (response.success) {
        response.directives.forEach { directive ->
            when (directive.type) {
                "toast" -> showSnackbar(directive.message ?: "")
                "mutate_cart" -> {
                    if (directive.discountPercent != null) {
                        CartRepository.applyCoupon(
                            code = directive.couponCode ?: "",
                            label = "Discount (${directive.discountPercent}%)",
                            discountPercent = directive.discountPercent.toDouble(),
                            maxDiscount = directive.maxDiscount?.toDouble() ?: 0.0
                        )
                    }
                }
                "open_sheet" -> openBottomSheet(directive.sheetName)
                "navigate" -> navController.navigate(directive.route)
            }
        }
    }
}
```

---

[← Previous: Example Workflows & Generators](05-built-in-workflows-catalog.md) | [📚 Docs Index](README.md) | [Next: Audit Logging & Analytics →](07-audit-logging-and-analytics.md)
