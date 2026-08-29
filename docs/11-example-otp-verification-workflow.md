# 11 - Example: Phone OTP Verification & Auth Workflow

[← Previous: Example: Appointment Booking Workflow](10-example-appointment-booking-workflow.md) | [📚 Docs Index](README.md) | [Next: Directive Capability Negotiation →](12-directive-capability-negotiation.md)

---

This example demonstrates how to orchestrate a secure **One-Time Password (OTP) verification** flow, issue a Laravel Sanctum API bearer token, and emit client directives to store the token and navigate to the authenticated home dashboard.

---

## 🎯 Business Scenario

1. A mobile user enters their phone number `+1 555-019-2834` and the 6-digit OTP code `482910`.
2. The server must:
   - Verify the OTP code against Redis/cache or database.
   - Prevent brute-force attempts with rate limiting.
   - Find or auto-register the `User` record.
   - Issue a new Laravel Sanctum bearer token.
   - Return directives to store the bearer token securely on the device and transition to the authenticated screen.

---

## 💻 1. The OTP Service (`OtpService.php`)

```php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    public function verifyAndLogin(string $phone, string $otpCode): array
    {
        $cacheKey = "otp_" . preg_replace('/[^0-9]/', '', $phone);
        $cachedOtp = Cache::get($cacheKey);

        // For testing / master bypass: '123456'
        $isValid = ($cachedOtp && $cachedOtp === $otpCode) || ($otpCode === '123456');

        if (!$isValid) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP code. Please request a new one.'
            ];
        }

        // Clear OTP once used
        Cache::forget($cacheKey);

        // Find or create User
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'Mobile Guest', 'email' => $phone . '@mobile.app']
        );

        // Issue Sanctum Bearer Token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone
            ]
        ];
    }
}
```

---

## 🛠️ 2. The Workflow Handler (`VerifyOtpWorkflow.php`)

```php
namespace App\Workflows;

use App\Services\OtpService;
use HashtagCms\Workflows\Contracts\WorkflowHandlerInterface;
use HashtagCms\Workflows\Engine\WorkflowContext;
use HashtagCms\Workflows\Engine\WorkflowResponse;

class VerifyOtpWorkflow implements WorkflowHandlerInterface
{
    public function __construct(protected OtpService $otpService) {}

    public function handle(WorkflowContext $context): WorkflowResponse
    {
        $phone = (string)$context->get('phone');
        $code = (string)$context->get('code');

        // 1. Validation
        if (empty($phone) || empty($code)) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast('Please provide both phone number and 6-digit OTP code.', 'error')
                ->haptic('error');
        }

        // 2. Delegate to OtpService
        $result = $this->otpService->verifyAndLogin($phone, $code);

        if (!$result['success']) {
            return WorkflowResponse::make()
                ->setSuccess(false)
                ->toast($result['message'], 'error')
                ->haptic('error');
        }

        // 3. Emit Auth Directives
        return WorkflowResponse::make()
            ->setSuccess(true)
            ->toast("👋 Welcome back, {$result['user']['name']}!", 'success')
            ->addDirective([
                'type' => 'set_auth',
                'token' => $result['token'],
                'user' => $result['user'],
            ])
            ->navigate('home', ['clearBackstack' => true])
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
    "workflow": "WORKFLOW_VERIFY_OTP",
    "payload": {
        "phone": "+1 555-019-2834",
        "code": "123456"
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
            "message": "👋 Welcome back, Mobile Guest!",
            "level": "success"
        },
        {
            "type": "set_auth",
            "token": "1|qX8zP1L...9834",
            "user": {
                "id": 14,
                "name": "Mobile Guest",
                "phone": "+1 555-019-2834"
            }
        },
        {
            "type": "navigate",
            "target": "home",
            "params": { "clearBackstack": true }
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

[← Previous: Example: Appointment Booking Workflow](10-example-appointment-booking-workflow.md) | [📚 Docs Index](README.md) | [Next: Directive Capability Negotiation →](12-directive-capability-negotiation.md)
