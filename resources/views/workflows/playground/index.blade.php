@extends(htcms_admin_config('theme').'.index')

@section('content')

    <title-bar data-title="Workflow Playground" data-show-copy="false" data-show-paste="false" data-back-url="{{ htcms_admin_path('workflows/builder') }}"></title-bar>

    {{-- v-pre: this subtree is driven by plain JS, not Vue --}}
    <div v-pre id="wf-playground"
         data-execute-url="{{ $executeUrl }}"
         data-site-id="{{ $siteId }}"
         class="max-w-6xl mx-auto">

        <style>
            #wf-playground .wf-card { transition: box-shadow .2s; }
            #wf-playground .wf-card:hover { box-shadow: 0 10px 30px rgba(15,23,42,.08); }
            #wf-playground textarea.wf-payload {
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px;
                width: 100%; min-height: 64px; resize: vertical;
                border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc; color: #0f172a;
            }
            #wf-playground pre.wf-json {
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; line-height: 1.5;
                background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 12px; overflow: auto; max-height: 320px; margin: 0;
            }
            #wf-playground .wf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); gap: 8px; }
            #wf-playground .wf-grid img { width: 100%; height: 72px; object-fit: cover; border-radius: 8px; }
            #wf-playground .wf-chip { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
            #wf-toast-wrap { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
            .wf-toast { min-width: 240px; max-width: 360px; padding: 12px 16px; border-radius: 10px; color: #fff;
                font: 600 13px/1.4 system-ui, sans-serif; box-shadow: 0 8px 24px rgba(0,0,0,.18);
                opacity: 0; transform: translateY(-8px); transition: opacity .25s, transform .25s; }
            .wf-toast.show { opacity: 1; transform: translateY(0); }
            .wf-toast.success { background: #16a34a; } .wf-toast.error { background: #dc2626; } .wf-toast.info { background: #2563eb; }
        </style>

        <div style="margin-bottom: 24px;">
            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 6px;">Server-Driven Workflows</h2>
            <p style="font-size: 13px; line-height: 1.6; color: #64748b; max-width: 760px; margin: 0;">
                Each card runs a workflow on the server. The backend executes the logic and returns
                <strong style="color: #334155;">directives</strong> — instructions this client renders (toasts, banners, galleries…).
                Edit the payload, hit <strong style="color: #334155;">Run</strong>, and watch the rendered directives and the raw JSON.
            </p>
        </div>

        @if($examples->isEmpty())
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-5 text-sm">
                No published workflows found. Seed the examples first:
                <code class="font-mono">php artisan db:seed --class="HashtagCms\Workflows\Database\Seeders\WorkflowExamplesSeeder"</code>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                @foreach($examples as $ex)
                    <div class="wf-card bg-white rounded-2xl border border-slate-100 shadow-sm p-5" data-alias="{{ $ex['alias'] }}">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $ex['name'] }}</h3>
                                <span class="inline-block mt-1 px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-mono font-bold rounded">{{ $ex['alias'] }}</span>
                            </div>
                            <button type="button"
                                    class="wf-run shrink-0 px-4 py-2 bg-slate-900 hover:bg-slate-700 text-white text-xs font-bold rounded-xl">
                                Run
                            </button>
                        </div>

                        @if($ex['description'])
                            <p class="text-xs text-slate-500 mb-3">{{ $ex['description'] }}</p>
                        @endif

                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">Payload</label>
                        <textarea class="wf-payload" spellcheck="false">{{ $ex['sample_payload'] }}</textarea>

                        <div class="wf-result hidden mt-4">
                            <div class="mb-3">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">Rendered directives</div>
                                <div class="wf-directives space-y-2"></div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">Request / Response JSON</div>
                                <pre class="wf-json"></pre>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div id="wf-toast-wrap"></div>

@endsection

@push('scripts')
<script>
(function () {
    var root = document.getElementById('wf-playground');
    if (!root) return;
    var EXECUTE_URL = root.dataset.executeUrl;
    var SITE_ID = parseInt(root.dataset.siteId || '1', 10);

    function toast(message, level) {
        var wrap = document.getElementById('wf-toast-wrap');
        var el = document.createElement('div');
        el.className = 'wf-toast ' + (level || 'info');
        el.textContent = message;
        wrap.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('show'); });
        setTimeout(function () { el.classList.remove('show'); setTimeout(function () { el.remove(); }, 300); }, 4000);
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Render one directive into the card's directives panel.
    function renderDirective(container, d, response) {
        var p = d.payload || {};
        var box = document.createElement('div');

        if (d.type === 'toast') {
            var level = p.level || d.level || 'info';
            var colors = { success: '#16a34a', error: '#dc2626', info: '#2563eb' };
            box.innerHTML = '<span class="wf-chip" style="background:' + (colors[level] || '#2563eb') + ';color:#fff">toast · ' + esc(level) + '</span> '
                + '<span style="font-size:13px;color:#334155">' + esc(p.message || d.message || '') + '</span>';
            toast(p.message || d.message || 'Done', level);

        } else if (d.type === 'show_welcome') {
            box.innerHTML = '<div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:10px;padding:12px 14px;font-size:13px">'
                + '<strong>Welcome!</strong> ' + esc(p.message || (response && response.message) || '') + '</div>';

        } else if (d.type === 'render_photos') {
            var items = p.items || d.items || [];
            var grid = items.slice(0, 12).map(function (it) {
                return '<img src="' + esc(it.download_url || it.url || '') + '" alt="' + esc(it.author || '') + '" loading="lazy">';
            }).join('');
            box.innerHTML = '<div style="font-size:12px;color:#64748b;margin-bottom:6px">' + items.length + ' photos</div><div class="wf-grid">' + grid + '</div>';

        } else {
            // Generic client directive (navigate, open_sheet, haptic, mutate_cart, custom…)
            box.innerHTML = '<span class="wf-chip" style="background:#e2e8f0;color:#334155">' + esc(d.type) + '</span> '
                + '<code style="font-size:11px;color:#64748b">' + esc(JSON.stringify(p)) + '</code>';
        }
        container.appendChild(box);
    }

    function run(card) {
        var alias = card.dataset.alias;
        var payloadEl = card.querySelector('.wf-payload');
        var resultEl = card.querySelector('.wf-result');
        var directivesEl = card.querySelector('.wf-directives');
        var jsonEl = card.querySelector('.wf-json');
        var btn = card.querySelector('.wf-run');

        var payload = {};
        try { payload = payloadEl.value.trim() ? JSON.parse(payloadEl.value) : {}; }
        catch (e) { toast('Invalid JSON in payload', 'error'); return; }

        var requestBody = { workflow: alias, payload: payload, site_id: SITE_ID, platform: 'web' };

        resultEl.classList.remove('hidden');
        directivesEl.innerHTML = '<span style="font-size:12px;color:#94a3b8">Running…</span>';
        jsonEl.textContent = '';
        btn.disabled = true; btn.textContent = '…';

        fetch(EXECUTE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(requestBody)
        })
        .then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
        .then(function (res) {
            var data = res.data || {};
            directivesEl.innerHTML = '';
            (data.directives || []).forEach(function (d) { renderDirective(directivesEl, d, data); });
            if (!(data.directives || []).length) {
                directivesEl.innerHTML = '<span style="font-size:12px;color:#94a3b8">No directives returned.</span>';
            }
            jsonEl.textContent = '// REQUEST\n' + JSON.stringify(requestBody, null, 2)
                + '\n\n// RESPONSE (HTTP ' + res.status + ')\n' + JSON.stringify(data, null, 2);
        })
        .catch(function (err) {
            directivesEl.innerHTML = '<span style="font-size:12px;color:#dc2626">Request failed.</span>';
            jsonEl.textContent = String(err);
            toast('Request failed', 'error');
        })
        .finally(function () { btn.disabled = false; btn.textContent = 'Run'; });
    }

    root.querySelectorAll('.wf-run').forEach(function (btn) {
        btn.addEventListener('click', function () { run(btn.closest('.wf-card')); });
    });
})();
</script>
@endpush
