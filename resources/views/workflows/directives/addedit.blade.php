@extends(htcms_admin_config('theme').'.index')

@section('content')

    <title-bar data-title="{!! htcms_get_module_name(request()->module_info) !!}"
               data-back-url="{{$backURL ?? htcms_admin_path('workflows/directives')}}"
    ></title-bar>

    @php
        $id = 0;
        $type = old('type');
        $label = old('label');
        $category = old('category');
        $description = old('description');
        $platforms = old('platforms');
        $schema = old('schema');
        $fallback = old('fallback');
        $publish_status = old('publish_status', 1);
        $site_id = old('site_id', htcms_get_siteId_for_admin());
        $actionPerformed = $actionPerformed ?? ($id > 0 ? 'edit' : 'add');
        $backURL = $backURL ?? htcms_admin_path('workflows/directives');

        if (isset($results)) {
            if (is_array($results)) {
                extract($results);
            } elseif (is_object($results) && method_exists($results, 'toArray')) {
                extract($results->toArray());
            }

            if (isset($results['platforms']) && is_array($results['platforms'])) {
                $platforms = json_encode($results['platforms'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            if (isset($results['schema']) && is_array($results['schema'])) {
                $schema = json_encode($results['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        if (is_array($platforms)) {
            $platforms = json_encode($platforms, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($schema)) {
            $schema = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    @endphp

    <div v-pre class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden max-w-4xl mx-auto">
        <!-- Card Header -->
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Directive Capability Manifest</h3>
                <p class="text-xs text-slate-500 mt-1">Declare a client directive, which platforms can render it, and its fallback</p>
            </div>
            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-lg">HashtagCMS Workflows</span>
        </div>

        <form action="{{htcms_get_save_path(request()->module_info->controller_name ?? 'workflows/directives')}}" method="post" id="addEditForm">
            <div class="p-8 lg:p-10 space-y-6">
                {{csrf_field()}}
                {!! FormHelper::input('hidden', 'id', $id) !!}
                {!! FormHelper::input('hidden', 'backURL', $backURL) !!}
                {!! FormHelper::input('hidden', 'actionPerformed', $actionPerformed) !!}
                {!! FormHelper::input('hidden', 'site_id', $site_id) !!}

                <!-- Identity -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        {!! FormHelper::label('type', 'Directive Type (canonical key)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'type', $type, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-mono font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'required'=>'required', 'placeholder'=>'e.g. mutate_cart')) !!}
                    </div>

                    <div class="space-y-2">
                        {!! FormHelper::label('label', 'Display Label', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'label', $label, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'required'=>'required', 'placeholder'=>'e.g. Mutate cart')) !!}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        {!! FormHelper::label('category', 'Category', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'category', $category, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-medium text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'placeholder'=>'e.g. cart, navigation, feedback')) !!}
                    </div>

                    <div class="space-y-2">
                        {!! FormHelper::label('fallback', 'Fallback Directive Type (optional)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                        {!! FormHelper::input('text', 'fallback', $fallback, array('class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs font-mono font-medium text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'placeholder'=>'e.g. navigate')) !!}
                    </div>
                </div>

                <div class="space-y-2">
                    {!! FormHelper::label('description', 'Description', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                    {!! FormHelper::textarea('description', $description, array('rows'=>2, 'class'=>'form-control w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-xs text-gray-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none', 'placeholder'=>'What this directive does on the client...')) !!}
                </div>

                <!-- Platforms map -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            {!! FormHelper::label('platforms', 'Platform Support (JSON)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                            <p class="text-[11px] text-slate-400">Map of <code>platform → minimum app version</code>. Leave blank for "supported everywhere, any version".</p>
                        </div>
                        <button type="button" onclick="loadPlatformsSample()" class="px-2.5 py-1 text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium">Sample</button>
                    </div>
                    <textarea name="platforms" id="platforms" rows="5" class="w-full bg-white text-slate-900 font-mono text-xs p-4 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none leading-relaxed shadow-sm" placeholder='{ "web": "1.0", "android": "2.1", "ios": "2.1" }'>{{ $platforms }}</textarea>
                </div>

                <!-- Payload schema -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            {!! FormHelper::label('schema', 'Payload Schema (JSON)', array('class' => 'text-sm font-semibold text-slate-700 block')) !!}
                            <p class="text-[11px] text-slate-400">Field spec for this directive's payload — used by validation and the Playground.</p>
                        </div>
                        <button type="button" onclick="loadSchemaSample()" class="px-2.5 py-1 text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium">Sample</button>
                    </div>
                    <textarea name="schema" id="schema" rows="5" class="w-full bg-white text-slate-900 font-mono text-xs p-4 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none leading-relaxed shadow-sm" placeholder='{ "message": "string", "level": "enum:success,error,info,warning" }'>{{ $schema }}</textarea>
                </div>

                <!-- Toggle -->
                <div class="grid grid-cols-1 pt-4 border-t border-slate-100">
                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
                        {!! FormHelper::input('checkbox', 'publish_status', $publish_status) !!}
                        <div>
                            {!! FormHelper::label('publish_status', 'Publish Status', array('class'=>'text-xs font-bold text-slate-800 cursor-pointer block')) !!}
                            <span class="text-[10px] text-slate-400">Directive is active and considered during capability negotiation</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-end gap-4">
                <a href="{{$backURL}}" class="w-full sm:w-auto text-center px-6 py-3.5 text-xs font-black uppercase tracking-widest text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors order-2 sm:order-1">Cancel</a>
                <button type="submit" name="submit" class="w-full sm:w-auto px-10 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest rounded-xl shadow-xl shadow-indigo-600/20 transition-all active:scale-95 flex items-center justify-center gap-2 order-1 sm:order-2">
                    <i class="fa fa-save opacity-50"></i>
                    Save Directive
                </button>
            </div>
        </form>
    </div>

    @include(htcms_admin_get_view_path('common.validationerror-js'))

    <script>
        function loadPlatformsSample() {
            var sample = { "web": "1.0", "android": "2.1", "ios": "2.1" };
            document.getElementById('platforms').value = JSON.stringify(sample, null, 4);
        }
        function loadSchemaSample() {
            var sample = { "action": "string", "couponCode": "string?", "discountPercent": "int?" };
            document.getElementById('schema').value = JSON.stringify(sample, null, 4);
        }
    </script>
@endsection
