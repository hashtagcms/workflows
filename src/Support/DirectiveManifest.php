<?php

namespace HashtagCms\Workflows\Support;

/**
 * The canonical list of directive types this package ships and knows how to
 * emit. This is the single source of truth consumed by both the seeding
 * migration and {@see \HashtagCms\Workflows\Database\Seeders\WorkflowDirectivesSeeder}.
 *
 * Each entry declares:
 *  - type:        canonical directive name (the natural key, unique per site)
 *  - label:       human-friendly name for the admin UI
 *  - category:    grouping for the admin directive picker
 *  - platforms:   map of platform => minimum app version that can render it.
 *                 null / omitted = supported on every platform, any version.
 *  - schema:      payload field spec (used for validation)
 *  - fallback:    the `type` of another directive to substitute when a client
 *                 cannot render this one (resolved as a chain by the negotiator)
 *
 * See docs/12-directive-capability-negotiation.md for the full contract.
 */
class DirectiveManifest
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function core(): array
    {
        return [
            // ---- Feedback ------------------------------------------------------
            self::d('toast', 'Toast message', 'feedback', 'A transient message shown to the user.',
                null, ['message' => 'string', 'level' => 'enum:success,error,info,warning']),
            self::d('alert', 'Alert dialog', 'feedback', 'A blocking alert dialog with a title and message.',
                null, ['title' => 'string', 'message' => 'string'], 'toast'),
            self::d('snackbar', 'Snackbar', 'feedback', 'A snackbar with an optional action button.',
                null, ['message' => 'string', 'actionLabel' => 'string?'], 'toast'),
            self::d('banner', 'Inline banner', 'feedback', 'A persistent inline banner.',
                null, ['message' => 'string', 'level' => 'enum:success,error,info,warning'], 'toast'),
            self::d('haptic', 'Haptic feedback', 'feedback', 'Physical haptic feedback on supported devices.',
                ['android' => '1.0', 'ios' => '1.0'], ['intensity' => 'enum:success,error,warning,medium']),
            self::d('play_sound', 'Play sound', 'feedback', 'Play a short UI sound.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['sound' => 'string']),

            // ---- Navigation ----------------------------------------------------
            self::d('navigate', 'Navigate', 'navigation', 'Route the client to a named destination.',
                null, ['target' => 'string', 'params' => 'object?']),
            self::d('go_back', 'Go back', 'navigation', 'Pop the current screen / navigate back.',
                null, []),
            self::d('switch_tab', 'Switch tab', 'navigation', 'Select a bottom/side navigation tab.',
                null, ['tab' => 'string'], 'navigate'),
            self::d('scroll_to', 'Scroll to', 'navigation', 'Scroll to an anchor or element.',
                null, ['anchor' => 'string']),
            self::d('open_url', 'Open URL', 'navigation', 'Open an external URL in the browser.',
                null, ['url' => 'string']),
            self::d('deep_link', 'Deep link', 'navigation', 'Follow an in-app deep link URI.',
                ['android' => '1.0', 'ios' => '1.0'], ['uri' => 'string'], 'navigate'),
            self::d('open_sheet', 'Open sheet', 'navigation', 'Present a modal bottom sheet / drawer.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['sheetId' => 'string', 'payload' => 'object?'], 'navigate'),
            self::d('dismiss_sheet', 'Dismiss sheet', 'navigation', 'Dismiss the current sheet / drawer.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], []),
            self::d('open_modal', 'Open modal', 'navigation', 'Present a modal dialog.',
                null, ['modalId' => 'string', 'payload' => 'object?'], 'open_sheet'),
            self::d('dismiss_modal', 'Dismiss modal', 'navigation', 'Dismiss the current modal.',
                null, []),

            // ---- Cart & commerce ----------------------------------------------
            self::d('mutate_cart', 'Mutate cart', 'cart', 'Add, remove, or apply changes to the shopping cart.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['action' => 'string', 'couponCode' => 'string?', 'discountPercent' => 'int?'], 'toast'),
            self::d('update_badge', 'Update badge', 'cart', 'Update a numeric badge (e.g. cart count).',
                null, ['key' => 'string', 'count' => 'int']),
            self::d('set_wishlist', 'Set wishlist', 'cart', 'Add or remove an item from the wishlist.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['action' => 'enum:add,remove', 'productId' => 'string'], 'toast'),

            // ---- Content -------------------------------------------------------
            self::d('render_photos', 'Render photos', 'content', 'Render an image grid from a list payload.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['action' => 'string', 'items' => 'array'], 'toast'),
            self::d('render_list', 'Render list', 'content', 'Render a generic list from a data payload.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['items' => 'array'], 'toast'),
            self::d('show_welcome', 'Show welcome banner', 'content', 'Display a welcome banner sourced from a target response.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['message' => 'string'], 'banner'),

            // ---- State ---------------------------------------------------------
            self::d('update_state', 'Update state', 'state', 'Merge values into the client state store.',
                null, ['path' => 'string', 'value' => 'any']),
            self::d('set_value', 'Set value', 'state', 'Set a single key in local/session storage.',
                null, ['key' => 'string', 'value' => 'any']),
            self::d('refresh', 'Refresh', 'state', 'Refresh the current screen or a named region.',
                null, ['region' => 'string?']),
            self::d('reload', 'Reload', 'state', 'Force a full reload of the client.',
                null, []),
            self::d('invalidate_cache', 'Invalidate cache', 'state', 'Invalidate a cached key on the client.',
                null, ['key' => 'string']),

            // ---- Auth ----------------------------------------------------------
            self::d('set_auth', 'Set auth', 'auth', 'Store an authentication token / session.',
                null, ['token' => 'string', 'user' => 'object?']),
            self::d('clear_auth', 'Clear auth', 'auth', 'Clear the authentication token / session.',
                null, []),
            self::d('request_biometric', 'Request biometric', 'auth', 'Prompt for biometric authentication.',
                ['android' => '1.0', 'ios' => '1.0'], ['reason' => 'string?']),

            // ---- Device --------------------------------------------------------
            self::d('copy_to_clipboard', 'Copy to clipboard', 'device', 'Copy a value to the clipboard.',
                null, ['value' => 'string']),
            self::d('share', 'Share', 'device', 'Open the native/web share sheet.',
                null, ['title' => 'string?', 'text' => 'string?', 'url' => 'string?'], 'copy_to_clipboard'),
            self::d('request_review', 'Request review', 'device', 'Prompt for an app-store review.',
                ['android' => '1.0', 'ios' => '1.0'], []),

            // ---- Analytics -----------------------------------------------------
            self::d('track_event', 'Track event', 'analytics', 'Emit an analytics event on the client.',
                null, ['name' => 'string', 'properties' => 'object?']),

            // ---- Feedback & UI state (added) -----------------------------------
            self::d('show_loader', 'Show loader', 'feedback', 'Show a loading overlay while something runs.',
                null, ['message' => 'string?']),
            self::d('hide_loader', 'Hide loader', 'feedback', 'Dismiss the loading overlay.',
                null, []),
            self::d('progress', 'Progress', 'feedback', 'Update a progress bar (0–100).',
                null, ['value' => 'int', 'label' => 'string?']),
            self::d('confetti', 'Confetti', 'feedback', 'Play a celebratory confetti animation.',
                null, []),
            self::d('confirm', 'Confirm dialog', 'feedback', 'Ask the user to confirm; can run another workflow on the answer.',
                null, ['title' => 'string', 'message' => 'string?', 'confirmLabel' => 'string?', 'cancelLabel' => 'string?', 'onConfirm' => 'string?'], 'alert'),
            self::d('coachmark', 'Coach mark', 'feedback', 'Highlight a UI element with an onboarding hint.',
                null, ['anchor' => 'string', 'text' => 'string'], 'toast'),
            self::d('set_field_error', 'Set field error', 'feedback', 'Attach an inline error to a form field.',
                null, ['field' => 'string', 'message' => 'string'], 'toast'),

            // ---- Navigation (added) -------------------------------------------
            self::d('replace', 'Replace screen', 'navigation', 'Navigate, replacing the current screen (no back).',
                null, ['target' => 'string', 'params' => 'object?'], 'navigate'),
            self::d('pop_to_root', 'Pop to root', 'navigation', 'Return to the root of the navigation stack.',
                null, [], 'go_back'),
            self::d('call_phone', 'Call phone', 'navigation', 'Dial a phone number.',
                ['android' => '1.0', 'ios' => '1.0'], ['phone' => 'string'], 'toast'),
            self::d('open_email', 'Open email', 'navigation', 'Compose an email to an address.',
                null, ['to' => 'string', 'subject' => 'string?', 'body' => 'string?'], 'open_url'),
            self::d('open_map', 'Open map', 'navigation', 'Open maps to an address or coordinates.',
                null, ['query' => 'string?', 'lat' => 'string?', 'lng' => 'string?'], 'open_url'),
            self::d('open_settings', 'Open settings', 'navigation', 'Open the app/device settings screen.',
                ['android' => '1.0', 'ios' => '1.0'], ['section' => 'string?']),

            // ---- Commerce (added) ---------------------------------------------
            self::d('start_checkout', 'Start checkout', 'cart', 'Begin the checkout flow.',
                null, [], 'navigate'),
            self::d('clear_cart', 'Clear cart', 'cart', 'Empty the shopping cart.',
                null, [], 'toast'),
            self::d('update_quantity', 'Update quantity', 'cart', 'Change the quantity of a cart line item.',
                null, ['productId' => 'string', 'quantity' => 'int'], 'mutate_cart'),
            self::d('notify_back_in_stock', 'Back-in-stock alert', 'cart', 'Subscribe the user to a restock alert.',
                null, ['productId' => 'string'], 'toast'),
            self::d('track_order', 'Track order', 'cart', 'Open order tracking for an order.',
                null, ['orderId' => 'string'], 'navigate'),

            // ---- Payments (added) ---------------------------------------------
            self::d('open_payment_sheet', 'Open payment sheet', 'payments', 'Present a native/web payment sheet.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['provider' => 'enum:apple_pay,google_pay,stripe,razorpay', 'amount' => 'int?', 'currency' => 'string?'], 'navigate'),
            self::d('add_payment_method', 'Add payment method', 'payments', 'Prompt the user to add a card/method.',
                null, [], 'navigate'),
            self::d('show_paywall', 'Show paywall', 'payments', 'Present a subscription paywall.',
                null, ['planId' => 'string?'], 'navigate'),

            // ---- Content & theming (added) ------------------------------------
            self::d('render_component', 'Render component', 'content', 'Render an SDUI component tree.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['component' => 'string', 'props' => 'object?'], 'render_list'),
            self::d('update_component', 'Update component', 'content', 'Patch an already-rendered component.',
                ['web' => '1.0', 'android' => '1.0', 'ios' => '1.0'], ['id' => 'string', 'props' => 'object?']),
            self::d('set_theme', 'Set theme', 'content', 'Switch the app theme.',
                null, ['theme' => 'enum:light,dark,system']),
            self::d('set_locale', 'Set locale', 'content', 'Change the app language/locale.',
                null, ['locale' => 'string']),

            // ---- Device & permissions (added) ---------------------------------
            self::d('request_permission', 'Request permission', 'device', 'Prompt for a device permission.',
                ['android' => '1.0', 'ios' => '1.0'], ['permission' => 'enum:camera,location,notifications,contacts,photos']),
            self::d('schedule_notification', 'Schedule notification', 'device', 'Schedule a local notification.',
                ['android' => '1.0', 'ios' => '1.0'], ['title' => 'string', 'body' => 'string?', 'at' => 'string?']),
            self::d('add_to_calendar', 'Add to calendar', 'device', 'Add an event to the device calendar.',
                ['android' => '1.0', 'ios' => '1.0'], ['title' => 'string', 'startsAt' => 'string', 'endsAt' => 'string?', 'location' => 'string?']),
            self::d('scan_qr', 'Scan QR', 'device', 'Open the QR scanner.',
                ['android' => '1.0', 'ios' => '1.0'], []),
            self::d('scan_barcode', 'Scan barcode', 'device', 'Open the barcode scanner.',
                ['android' => '1.0', 'ios' => '1.0'], []),
            self::d('download_file', 'Download file', 'device', 'Download/save a file.',
                null, ['url' => 'string', 'filename' => 'string?'], 'open_url'),
            self::d('get_location', 'Get location', 'device', 'Fetch the current device location.',
                ['android' => '1.0', 'ios' => '1.0'], []),

            // ---- Flow / orchestration (added) ---------------------------------
            self::d('run_workflow', 'Run workflow', 'flow', 'Trigger another workflow by alias (compose workflows).',
                null, ['workflow' => 'string', 'payload' => 'object?']),
            self::d('delay', 'Delay', 'flow', 'Wait before the next directive runs.',
                null, ['ms' => 'int']),
            self::d('emit_event', 'Emit event', 'flow', 'Fire a client-side event on the app event bus.',
                null, ['name' => 'string', 'data' => 'object?']),
            self::d('set_flag', 'Set flag', 'flow', 'Set a feature/session flag on the client.',
                null, ['key' => 'string', 'value' => 'any']),

            // ---- Growth (added) -----------------------------------------------
            self::d('register_push', 'Register push', 'growth', 'Register the device for push notifications.',
                ['android' => '1.0', 'ios' => '1.0'], []),
            self::d('share_referral', 'Share referral', 'growth', 'Open a referral/share flow.',
                null, ['code' => 'string?', 'message' => 'string?'], 'share'),
        ];
    }

    /**
     * Compact builder for a directive definition.
     *
     * @param array<string,string>|null $platforms
     * @param array<string,mixed>|null  $schema
     */
    private static function d(
        string $type,
        string $label,
        string $category,
        string $description,
        ?array $platforms = null,
        ?array $schema = null,
        ?string $fallback = null
    ): array {
        return [
            'type'        => $type,
            'label'       => $label,
            'category'    => $category,
            'description' => $description,
            'platforms'   => $platforms,
            'schema'      => $schema,
            'fallback'    => $fallback,
        ];
    }
}
