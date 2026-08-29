<?php

if (!function_exists('htcms_workflows_view')) {
    /**
     * Load a HashtagCMS Workflows admin view.
     *
     * Thin convenience wrapper: it namespaces the given view with the package's
     * view prefix (unless the caller already used a `::` namespace) and then hands
     * off to core's admin view loader `htcms_admin_view()`, which does the actual
     * `view()->first()` resolution and the built-in `common.error` fallback.
     *
     * @param string $name
     * @param array $data
     * @param bool $isAjax
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    function htcms_workflows_view($name, $data = [], $isAjax = false)
    {
        if ($isAjax) {
            return response()->json($data, 200);
        }

        if (strpos($name, '::') === false) {
            $viewPrefix = config('hashtagcms-workflows.view_prefix', 'hashtagcms-workflows::be.workflows');
            if (!empty($viewPrefix)) {
                $name = rtrim($viewPrefix, '.') . '.' . ltrim($name, '.');
            }
        }

        return htcms_admin_view($name, $data);
    }
}

if (!function_exists('htcms_get_workflows_admin_theme')) {
    function htcms_get_workflows_admin_theme()
    {
        return htcms_admin_theme("modern");
    }
}
