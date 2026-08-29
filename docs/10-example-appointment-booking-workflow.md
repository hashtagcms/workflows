# 10 - Example: Salon & Clinic Appointment Booking Workflow

[← Previous: Example: Add to Cart Workflow](09-example-add-to-cart-workflow.md) | [📚 Docs Index](README.md) | [Next: Example: Phone OTP & Auth Workflow →](11-example-otp-verification-workflow.md)

---

This example demonstrates how to orchestrate a luxury multi-step appointment booking flow with stylist availability validation, database persistence, and client navigation directives.

---

## 🎯 Business Scenario

1. A customer selects **Balayage + Gloss ($285)**, chooses **Master Stylist Elena**, picks **Tomorrow at 2:00 PM**, and taps **"Confirm & Reserve"**.
2. The server must:
   - Verify stylist schedule availability for the requested time window (120 mins).
   - Create the booking reservation in the database with status `confirmed` or `deposit_pending`.
   - Send confirmation email / SMS alerts via background jobs.
   - Return a `navigate` directive to transition the client app directly to the luxury Confirmation screen.

---

## 💻 1. The Booking Service (`BookingService.php`)

```php
namespace App\Services;

class BookingService
{
    public function reserveAppointment(array $data, ?int $userId = null): array
    {
        $serviceId = (int)$data['serviceId'];
        $stylistId = (int)$data['stylistId'];
        $dateTime = $data['dateTime']; // e.g. "2026-08-25 14:00:00"

        $service = \App\Models\SalonService::find($serviceId);
        $stylist = \App\Models\Stylist::find($stylistId);

        if (!$service || !$stylist) {
            return ['success' => false, 'message' => 'Service or stylist selection is invalid.'];
        }

        // 1. Check double-booking conflicts
        $hasConflict = \App\Models\Appointment::where('stylist_id', $stylistId)
            ->where('appointment_date', $dateTime)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($hasConflict) {
            return ['success' => false, 'message' => "Elena is no longer available at this time slot. Please choose another time."];
        }

        // 2. Create the reservation
        $appointment = \App\Models\Appointment::create([
            'user_id' => $userId,
            'client_name' => $data['clientName'] ?? 'Guest Client',
            'client_phone' => $data['clientPhone'] ?? '',
            'service_id' => $serviceId,
            'stylist_id' => $stylistId,
            'appointment_date' => $dateTime,
            'total_amount' => $service->base_price,
            'status' => 'confirmed',
            'notes' => $data['notes'] ?? null
        ]);

        return [
            'success' => true,
            'bookingId' => $appointment->id,
            'serviceName' => $service->name,
            'stylistName' => $stylist->name,
            'dateTime' => $dateTime,
            'totalAmount' => (float)$service->base_price
        ];
    }
}
```

---

## 🛠️ 2. The Workflow Handler (`ConfirmBookingWorkflow.php`)

```php
namespace App\Workflows;

use App\Services\BookingService;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

class ConfirmBookingWorkflow implements WorkflowHandlerInterface
{
    public function __construct(protected BookingService $bookingService) {}

    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $payload = $context->getPayload();
        $user = $context->getUser();

        // 1. Validate required fields
        if (empty($payload['serviceId']) || empty($payload['stylistId']) || empty($payload['dateTime'])) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast('Please complete all booking selections.', 'error')
                ->haptic('error');
        }

        // 2. Reserve appointment
        $result = $this->bookingService->reserveAppointment($payload, $user?->id);

        if (!$result['success']) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast($result['message'], 'error')
                ->haptic('error');
        }

        // 3. Emit Navigation and Toast Directives
        return WorkflowResponse::make()
            ->setSuccess(true)
            ->toast("✨ Appointment confirmed with {$result['stylistName']}!", 'success')
            ->directive('navigate', [
                'target' => 'booking-confirmation',
                'params' => [
                    'bookingId' => $result['bookingId'],
                    'serviceName' => $result['serviceName'],
                    'stylistName' => $result['stylistName'],
                    'dateTime' => $result['dateTime'],
                    'totalAmount' => $result['totalAmount']
                ]
            ])
            ->directive('mutate_cart', [
                'action' => 'clear'
            ])
            ->haptic('success');
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
    "workflow": "WORKFLOW_CONFIRM_BOOKING",
    "payload": {
        "serviceId": 1,
        "stylistId": 2,
        "dateTime": "2026-08-25 14:00:00",
        "clientName": "Sarah Jenkins",
        "clientPhone": "+1 555-019-2834"
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
            "message": "✨ Appointment confirmed with Elena Vance!",
            "level": "success"
        },
        {
            "type": "navigate",
            "target": "booking-confirmation",
            "params": {
                "bookingId": 482,
                "serviceName": "Signature Balayage + Gloss",
                "stylistName": "Elena Vance",
                "dateTime": "2026-08-25 14:00:00",
                "totalAmount": 285.0
            }
        },
        {
            "type": "mutate_cart",
            "action": "clear"
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

[← Previous: Example: Add to Cart Workflow](09-example-add-to-cart-workflow.md) | [📚 Docs Index](README.md) | [Next: Example: Phone OTP & Auth Workflow →](11-example-otp-verification-workflow.md)
