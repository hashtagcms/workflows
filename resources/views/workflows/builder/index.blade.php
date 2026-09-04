@extends(htcms_admin_config('theme').'.index')

@section('content')

    <title-bar data-title="Interactive Workflow Manager"
               data-back-url="{{ htcms_admin_path('workflows/builder') }}"
    ></title-bar>

    @php
        $wf = [];
        if (isset($results)) {
            $wf = is_array($results)
                ? $results
                : (method_exists($results, 'toArray') ? $results->toArray() : (array) $results);
        }

        $config = $wf['config'] ?? null;
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $siteId = $wf['site_id'] ?? htcms_get_siteId_for_admin();

        // SSO context for the identity picker/indicator. Guarded so the builder
        // still loads if the SSO module isn't installed/active.
        $ssoActive = false;
        $ssoProviders = [];
        $ssoDefaultAlias = null;
        try {
            $ssoRepo = app(\HashtagCms\Workflows\Identity\Sso\SsoProviderRepository::class);
            $ssoActive = $ssoRepo->isModuleActive();
            if ($ssoActive) {
                foreach ($ssoRepo->listForSite((int) $siteId) as $p) {
                    $ssoProviders[] = [
                        'alias' => $p->alias,
                        'name' => $p->name,
                        'driver' => $p->driver,
                        'is_master' => (int) $p->site_id !== (int) $siteId,
                    ];
                }
                $ssoDefaultAlias = optional($ssoRepo->forSite((int) $siteId))->alias;
            }
        } catch (\Throwable $e) {
            // Leave SSO context empty — the builder degrades to "local login".
        }

        $initial = [
            'id' => $wf['id'] ?? 0,
            'name' => $wf['name'] ?? '',
            'alias' => $wf['alias'] ?? '',
            'description' => $wf['description'] ?? '',
            'handler' => $wf['handler'] ?? '',
            'auth_required' => isset($wf['auth_required']) ? (int) $wf['auth_required'] : 0,
            'sso_provider_alias' => $wf['sso_provider_alias'] ?? '',
            'sso_module_active' => $ssoActive,
            'sso_providers' => $ssoProviders,
            'sso_default_alias' => $ssoDefaultAlias,
            'sso_none_value' => \HashtagCms\Workflows\Identity\SsoIdentityResolver::PROVIDER_NONE,
            'publish_status' => isset($wf['publish_status']) ? (int) $wf['publish_status'] : 1,
            'config' => $config,
            'site_id' => $siteId,
            'storeUrl' => htcms_get_save_path(request()->module_info->controller_name ?? 'workflows/builder'),
            'previewUrl' => htcms_admin_path('workflows/builder/preview'),
            'backUrl' => htcms_admin_path('workflows/builder'),
            'directivesUrl' => url(trim(config('hashtagcmsapi.route_prefix', 'api/hashtagcms'), '/') . '/public/workflows/v1/directives'),
            'executeUrl' => url(trim(config('hashtagcmsapi.route_prefix', 'api/hashtagcms'), '/') . '/public/workflows/v1/execute'),
            'csrf' => csrf_token(),
        ];

        // Cache-bust the compiled bundle: the asset URL is otherwise static, so a
        // rebuilt JS/CSS would keep serving the browser's cached copy. Version by
        // the dist file's mtime so every rebuild busts the cache automatically.
        $distDir = dirname((new \ReflectionClass(\HashtagCms\Workflows\HashtagCmsWorkflowsServiceProvider::class))->getFileName(), 2) . '/resources/dist';
        $assetVer = @filemtime($distDir . '/workflow-builder.js') ?: null;
    @endphp

    {{-- The admin runs its own Vue app on #app and compiles this content as its
         template (stripping <script> tags), so the builder mounts as a self-
         contained island: v-pre tells the admin Vue to leave this subtree alone,
         and the init payload rides in a base64 data-attribute (immune to the
         script-stripping and to HTML escaping) rather than a <script> block. --}}
    <div v-pre>
        <div id="wf-builder" data-init="{{ base64_encode(json_encode($initial)) }}"></div>
        <link rel="stylesheet" href="{{ route('hashtagcms.workflows.builder.asset', ['file' => 'workflow-builder.css', 'v' => $assetVer]) }}">
        <script src="{{ route('hashtagcms.workflows.builder.asset', ['file' => 'workflow-builder.js', 'v' => $assetVer]) }}"></script>
    </div>
@endsection
