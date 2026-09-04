@extends(htcms_admin_config('theme').'.index')

@section('content')

    <title-bar data-title="{!! htcms_get_module_name(request()->module_info) !!}"
               data-back-url="{{$backURL ?? htcms_admin_path('workflows/sso')}}"
    ></title-bar>

    @php
        $id = 0;
        $name = old('name');
        $alias = old('alias');
        $description = old('description');
        $driver = old('driver', 'opaque');
        $on_failure = old('on_failure', 'reject');
        $cache_ttl = old('cache_ttl', 300);
        $enabled = old('enabled', 1);
        $config = old('config');
        $publish_status = old('publish_status', 1);
        $site_id = old('site_id', htcms_get_siteId_for_admin());
        $actionPerformed = $actionPerformed ?? ($id > 0 ? 'edit' : 'add');
        $backURL = $backURL ?? htcms_admin_path('workflows/sso');

        if (isset($results)) {
            if (is_array($results)) {
                extract($results);
            } elseif (is_object($results) && method_exists($results, 'toArray')) {
                extract($results->toArray());
            }

            if (isset($results['config']) && is_array($results['config'])) {
                $config = json_encode($results['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        if (is_array($config)) {
            $config = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    @endphp

    <div v-pre class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden max-w-4xl mx-auto">
        <!-- Card Header -->
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">External Login / SSO Provider</h3>
                <p class="text-xs text-slate-500 mt-1">Verify a client credential against an external login service and map it to a workflow identity</p>
            </div>
            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-lg">HashtagCMS Workflows</span>
        </div>

        <form action="{{htcms_get_save_path(request()->module_info->controller_name ?? 'workflows/sso')}}" method="post" id="addEditForm">
            <div class="p-8 lg:p-10 space-y-6">
                {{csrf_field()}}
                {!! FormHelper::input('hidden', 'id', $id) !!}
                {!! FormHelper::input('hidden', 'backURL', $backURL) !!}
                {!! FormHelper::input('hidden', 'actionPerformed', $actionPerformed) !!}
                {!! FormHelper::input('hidden', 'site_id', $site_id) !!}

                <!-- Identity -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        {!! FormHelper::label('name', 'Provider Name', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'name', $name, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'required'=>'required', 'placeholder'=>'e.g. XYZ SSO')) !!}
                    </div>

                    <div class="space-y-2">
                        {!! FormHelper::label('alias', 'Alias (stable key)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'alias', $alias, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-mono font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'required'=>'required', 'placeholder'=>'e.g. xyzsite-sso')) !!}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        {!! FormHelper::label('driver', 'Driver', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        <select name="driver" id="driver" class="form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                            <option value="opaque" {{ $driver === 'opaque' ? 'selected' : '' }}>opaque (introspection call)</option>
                            <option value="jwt" {{ $driver === 'jwt' ? 'selected' : '' }}>jwt (local JWKS verify)</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        {!! FormHelper::label('on_failure', 'On Failure', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        <select name="on_failure" id="on_failure" class="form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                            <option value="reject" {{ $on_failure === 'reject' ? 'selected' : '' }}>reject (401 on invalid token)</option>
                            <option value="anonymous" {{ $on_failure === 'anonymous' ? 'selected' : '' }}>anonymous (run unauthenticated)</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        {!! FormHelper::label('cache_ttl', 'Cache TTL (seconds)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('number', 'cache_ttl', $cache_ttl, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-medium text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'min'=>'0', 'placeholder'=>'300')) !!}
                    </div>
                </div>

                <div class="space-y-2">
                    {!! FormHelper::label('description', 'Description', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                    {!! FormHelper::textarea('description', $description, array('rows'=>2, 'class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'placeholder'=>'What this provider is and when it applies...')) !!}
                </div>

                <!-- Credential Source -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <div>
                        {!! FormHelper::label('cred_source', 'Where the token comes from', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        <p class="text-[11px] text-slate-400">By default the client presents <code>Authorization: Bearer &lt;token&gt;</code>. If your login service carries the token in a different header (e.g. <code>sessiontoken</code>), point at it here — it becomes <code>@{{request.bearer_token}}</code> for the verify block.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            {!! FormHelper::label('cred_source', 'Source', array('class' => 'text-xs font-semibold text-slate-600 block')) !!}
                            <select id="cred_source" onchange="onCredSourceChange()" class="form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                                <option value="bearer">Authorization: Bearer (default)</option>
                                <option value="header">Custom header</option>
                            </select>
                        </div>
                        <div class="space-y-2" id="cred_header_wrap" style="display:none">
                            {!! FormHelper::label('cred_header', 'Header name', array('class' => 'text-xs font-semibold text-slate-600 block')) !!}
                            <input type="text" id="cred_header" oninput="syncCredentialToConfig()" placeholder="e.g. sessiontoken" class="form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-mono font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                        </div>
                        <div class="space-y-2" id="cred_prefix_wrap" style="display:none">
                            {!! FormHelper::label('cred_strip_prefix', 'Strip prefix (optional)', array('class' => 'text-xs font-semibold text-slate-600 block')) !!}
                            <input type="text" id="cred_strip_prefix" oninput="syncCredentialToConfig()" placeholder="e.g. 'Bearer '" class="form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-mono font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none">
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400">A configured header is authoritative — when set, the standard <code>Authorization</code> bearer is <em>not</em> used as a fallback. This control edits the <code>credential</code> key in the config below.</p>
                </div>

                <!-- Config JSON -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            {!! FormHelper::label('config', 'Provider Config (JSON)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                            <p class="text-[11px] text-slate-400">For <code>opaque</code>: a <code>verify</code> request block + <code>identity</code> mapping. For <code>jwt</code>: <code>jwks_url</code>/<code>issuer</code>/<code>audience</code> + <code>identity</code>. Use <code>@{{request.*}}</code>, <code>@{{response.*}}</code>, <code>@{{token.*}}</code>.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="loadConfigSample('opaque')" class="px-2.5 py-1 text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium">Opaque sample</button>
                            <button type="button" onclick="loadConfigSample('jwt')" class="px-2.5 py-1 text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium">JWT sample</button>
                        </div>
                    </div>
                    <textarea name="config" id="config" rows="14" class="w-full bg-white text-slate-900 font-mono text-xs p-4 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none leading-relaxed shadow-sm" placeholder='{ "verify": { ... }, "identity": { ... } }'>{{ $config }}</textarea>
                </div>

                <!-- Toggles -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
                        {!! FormHelper::input('checkbox', 'enabled', $enabled) !!}
                        <div>
                            {!! FormHelper::label('enabled', 'Enabled', array('class'=>'text-xs font-bold text-slate-800 cursor-pointer block')) !!}
                            <span class="text-[10px] text-slate-400">Provider participates in identity resolution for its site</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
                        {!! FormHelper::input('checkbox', 'publish_status', $publish_status) !!}
                        <div>
                            {!! FormHelper::label('publish_status', 'Publish Status', array('class'=>'text-xs font-bold text-slate-800 cursor-pointer block')) !!}
                            <span class="text-[10px] text-slate-400">Row is live (unpublished rows are ignored)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-4">
                <a href="{{$backURL}}" class="w-full sm:w-auto text-center px-6 py-3.5 text-xs font-black uppercase tracking-widest text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors order-2 sm:order-1">Cancel</a>
                <button type="submit" name="submit" class="w-full sm:w-auto px-10 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest rounded-xl shadow-xl shadow-indigo-600/20 transition-all active:scale-95 flex items-center justify-center gap-2 order-1 sm:order-2">
                    <i class="fa fa-save opacity-50"></i>
                    Save Provider
                </button>
            </div>
        </form>
    </div>

    @include(htcms_admin_get_view_path('common.validationerror-js'))

    <script>
        function loadConfigSample(kind) {
            var opaque = {
                verify: {
                    url: "http://xyzsite.in/sso/authenticate",
                    method: "POST",
                    headers: { "Accept": "application/json" },
                    body: { token: "@{{request.bearer_token}}" }
                },
                identity: {
                    user_id: "@{{response.body.data.user.id}}",
                    claims: {
                        email: "@{{response.body.data.user.email}}",
                        roles: "@{{response.body.data.user.roles}}"
                    }
                }
            };
            var jwt = {
                jwks_url: "http://xyzsite.in/sso/jwks",
                issuer: "xyzsite.in",
                audience: "workflows",
                identity: {
                    user_id: "@{{token.sub}}",
                    claims: {
                        email: "@{{token.email}}",
                        roles: "@{{token.roles}}"
                    }
                }
            };
            var sample = kind === 'jwt' ? jwt : opaque;
            document.getElementById('config').value = JSON.stringify(sample, null, 4);
            document.getElementById('driver').value = kind === 'jwt' ? 'jwt' : 'opaque';
            // Samples ship with the default Authorization bearer, so reflect that.
            loadCredentialFromConfig();
        }

        // --- Credential source (reads/writes the `credential` key in the config JSON) ---
        function parseConfig() {
            var txt = (document.getElementById('config').value || '').trim();
            if (txt === '') { return {}; }
            try { return JSON.parse(txt); } catch (e) { return null; }
        }

        function onCredSourceChange() {
            var isHeader = document.getElementById('cred_source').value === 'header';
            document.getElementById('cred_header_wrap').style.display = isHeader ? '' : 'none';
            document.getElementById('cred_prefix_wrap').style.display = isHeader ? '' : 'none';
            syncCredentialToConfig();
        }

        function syncCredentialToConfig() {
            var cfg = parseConfig();
            if (cfg === null) { return; } // malformed JSON — leave the raw config untouched
            if (document.getElementById('cred_source').value === 'header') {
                var header = document.getElementById('cred_header').value.trim();
                if (header === '') {
                    delete cfg.credential;
                } else {
                    var cred = { header: header };
                    // NB: do not trim strip_prefix — prefixes like "Bearer " carry a trailing space.
                    var prefix = document.getElementById('cred_strip_prefix').value;
                    if (prefix !== '') { cred.strip_prefix = prefix; }
                    cfg.credential = cred;
                }
            } else {
                delete cfg.credential;
            }
            document.getElementById('config').value = JSON.stringify(cfg, null, 4);
        }

        function loadCredentialFromConfig() {
            var cfg = parseConfig();
            if (cfg === null) { return; }
            var cred = cfg.credential;
            if (cred && cred.header) {
                document.getElementById('cred_source').value = 'header';
                document.getElementById('cred_header').value = cred.header;
                document.getElementById('cred_strip_prefix').value = cred.strip_prefix || '';
            } else {
                document.getElementById('cred_source').value = 'bearer';
            }
            var isHeader = document.getElementById('cred_source').value === 'header';
            document.getElementById('cred_header_wrap').style.display = isHeader ? '' : 'none';
            document.getElementById('cred_prefix_wrap').style.display = isHeader ? '' : 'none';
        }

        document.addEventListener('DOMContentLoaded', loadCredentialFromConfig);
    </script>
@endsection
