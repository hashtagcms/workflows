@extends(htcms_admin_config('theme').'.index')

@section('content')
    <div class="bg-white rounded-2xl p-10 max-w-xl mx-auto shadow-sm border border-red-100 text-center">
        <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa fa-exclamation-triangle text-xl"></i>
        </div>
        <h2 class="text-base font-bold text-slate-800 mb-2">{{$title ?? 'Error'}}</h2>
        <p class="text-xs text-slate-500 mb-6">{{$message ?? 'An error occurred while processing the workflow request.'}}</p>
        <a href="{{request()->headers->get('referer') ?? htcms_admin_path('workflows/builder')}}" class="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold inline-block">Go Back</a>
    </div>
@endsection
