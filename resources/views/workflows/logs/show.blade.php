@extends(htcms_admin_config('theme').'.index')

@section('content')

    <title-bar data-title="Workflow Execution Log #{{$log->id}}"
               data-back-url="{{htcms_admin_path('workflows/logs')}}"
    ></title-bar>

    <div v-pre class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden max-w-4xl mx-auto">
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-mono font-bold rounded-lg">{{$log->workflow_alias}}</span>
                <span class="px-2.5 py-1 text-xs font-bold rounded-md {{$log->is_success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}}">
                    {{$log->is_success ? 'SUCCESS' : 'FAILED'}}
                </span>
            </div>
            <span class="text-xs text-slate-500 font-mono">{{$log->execution_time_ms}}ms execution time</span>
        </div>

        <div class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-xs text-slate-400 block font-medium">Logged At</span>
                    <span class="text-xs font-bold text-slate-800">{{$log->created_at}}</span>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-xs text-slate-400 block font-medium">Site ID</span>
                    <span class="text-xs font-bold text-slate-800">{{$log->site_id}}</span>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="text-xs text-slate-400 block font-medium">Session ID</span>
                    <span class="text-xs font-mono font-bold text-slate-800 truncate block">{{$log->session_id ?? 'N/A'}}</span>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Request Payload</h4>
                <pre class="bg-white text-slate-900 border border-slate-200 p-4 rounded-xl text-xs font-mono overflow-x-auto shadow-sm">{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}</pre>
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Response Directives Emitted</h4>
                <pre class="bg-white text-slate-900 border border-slate-200 p-4 rounded-xl text-xs font-mono overflow-x-auto shadow-sm">{{ json_encode($log->response_directives, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>

        <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <a href="{{htcms_admin_path('workflows/logs')}}" class="px-6 py-2.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50">
                Back to Logs
            </a>
        </div>
    </div>

@endsection
