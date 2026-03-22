@extends('adminlte::page')

@section('title', 'UI Settings')

@section('content_header')
    <div class="card" style="background: var(--sidebar-bg); color: white; border: none; margin-bottom: 20px;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="d-none d-md-block text-light"><i class="fas fa-palette"></i> UI Settings</h1>
                    <h1 class="d-md-none text-light"><i class="fas fa-palette"></i> UI Settings</h1>
                    <p class="mb-0 text-light">Customize sidebar and navbar theme for your Finance .</p>
                </div>
                <a href="{{ route('settings.profile.show') }}" class="btn btn-light">
                    <i class="fas fa-user-cog mr-1"></i> Profile Settings
                </a>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('settings.general_settings.index') }}">General Settings</a></li>
                <li class="breadcrumb-item active text-dark" aria-current="page">UI Settings</li>
            </ol>
        </nav>
        <a href="{{ route('settings.general_settings.index') }}" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>

    </div>
@stop

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Choose a Theme</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Select a preset color theme for the sidebar and top navigation bar. Changes apply after confirmation.
                    </p>

                    <div class="d-flex flex-wrap gap-3">
                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Default</h5>
                            <div class="mb-2 small text-muted">Use system defaults</div>
                            <button class="btn btn-sm btn-secondary btn-apply-theme" data-theme="default">Apply</button>
                        </div>

                          <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Corporate</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#00b4db,#0083b0);"></div>
                            <button class="btn btn-sm btn-primary btn-apply-theme" data-theme="corporate">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Teal</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#14b8a6,#0d9488);"></div>
                            <button class="btn btn-sm btn-info btn-apply-theme" data-theme="teal">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Indigo</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#6366f1,#4338ca);"></div>
                            <button class="btn btn-sm btn-primary btn-apply-theme" data-theme="indigo">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Slate</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#475569,#334155);"></div>
                            <button class="btn btn-sm btn-dark btn-apply-theme" data-theme="slate">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Masculine</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#141e30,#243b55);"></div>
                            <button class="btn btn-sm btn-primary btn-apply-theme" data-theme="masculine">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Neutral</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#bdc3c7,#2c3e50);"></div>
                            <button class="btn btn-sm btn-secondary btn-apply-theme" data-theme="neutral">Apply</button>
                        </div>


                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Green</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#10b981,#047857);"></div>
                            <button class="btn btn-sm btn-success btn-apply-theme" data-theme="green">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Gold</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#b45309,#92400e);"></div>
                            <button class="btn btn-sm btn-warning btn-apply-theme" data-theme="gold">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Purple</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#8e2de2,#4a00e0);"></div>
                            <button class="btn btn-sm btn-primary btn-apply-theme" data-theme="purple">Apply</button>
                        </div>

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Pink</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#db2777,#a21caf);"></div>
                            <button class="btn btn-sm btn-info btn-apply-theme" data-theme="pink">Apply</button>
                        </div>
                        

                        <div class="theme-card border rounded p-3 m-2" style="min-width:220px;max-width:260px;">
                            <h5>Feminine</h5>
                            <div class="mb-2" style="height:34px;background:linear-gradient(90deg,#c026d3,#7e22ce);"></div>
                            <button class="btn btn-sm btn-pink btn-apply-theme" data-theme="feminine">Apply</button>
                        </div>

    
                      
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Current Theme</h3>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Sidebar:</strong> <code id="currentSidebarBg">{{ optional($settings)->sidebar_bg ?: 'default' }}</code></div>
                    <div class="mb-2"><strong>Navbar:</strong> <code id="currentNavbarBg">{{ optional($settings)->navbar_bg ?: 'default' }}</code></div>
                    <p class="text-muted small">Changing theme updates these values.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('css')
    <style id="ui-theme-vars">
        :root{
            @if(!empty(optional($settings)->sidebar_bg)) --sidebar-bg: {{ optional($settings)->sidebar_bg }}; @endif
            @if(!empty(optional($settings)->navbar_bg)) --navbar-bg: {{ optional($settings)->navbar_bg }}; @endif
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
@endpush
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const saveUrl = @json(route('settings.ui.save'));

    const THEMES = {
        default: { sidebar_bg: '', navbar_bg: '' },
        green: {
            sidebar_bg: 'linear-gradient(180deg,#065f46,#064e3b)',
            navbar_bg: 'linear-gradient(135deg,#10b981,#047857)'
        },
        gold: {
            sidebar_bg: 'linear-gradient(180deg,#7c2d12,#92400e)',
            navbar_bg: 'linear-gradient(135deg,#b45309,#92400e)'
        },
        purple: {
            sidebar_bg: 'linear-gradient(180deg,#6a11cb,#3a0ca3)',
            navbar_bg: 'linear-gradient(135deg,#8e2de2,#4a00e0)'
        },
        pink: {
            sidebar_bg: 'linear-gradient(180deg,#be185d,#9d174d)',
            navbar_bg: 'linear-gradient(135deg,#db2777,#a21caf)'
        },
        teal: {
            sidebar_bg: 'linear-gradient(180deg,#134e4a,#0f766e)',
            navbar_bg: 'linear-gradient(135deg,#14b8a6,#0d9488)'
        },
        indigo: {
            sidebar_bg: 'linear-gradient(180deg,#312e81,#1e1b4b)',
            navbar_bg: 'linear-gradient(135deg,#6366f1,#4338ca)'
        },
        slate: {
            sidebar_bg: 'linear-gradient(180deg,#1e293b,#0f172a)',
            navbar_bg: 'linear-gradient(135deg,#475569,#334155)'
        },
        masculine: {
            sidebar_bg: 'linear-gradient(180deg,#0f2027,#203a43,#2c5364)',
            navbar_bg: 'linear-gradient(135deg,#141e30,#243b55)'
        },
        feminine: {
            sidebar_bg: 'linear-gradient(180deg,#a21caf,#6d28d9)',
            navbar_bg: 'linear-gradient(135deg,#c026d3,#7e22ce)'
        },
        neutral: {
            sidebar_bg: 'linear-gradient(180deg,#434343,#000000)',
            navbar_bg: 'linear-gradient(135deg,#bdc3c7,#2c3e50)'
        },
        corporate: {
            sidebar_bg: 'linear-gradient(180deg,#005C97,#363795)',
            navbar_bg: 'linear-gradient(135deg,#00b4db,#0083b0)'
        }
    };

    function applyPreview(values){
        const el = document.getElementById('ui-theme-vars');
        let css = ':root{';
        if (values.sidebar_bg) css += `--sidebar-bg:${values.sidebar_bg};`;
        if (values.navbar_bg) css += `--navbar-bg:${values.navbar_bg};`;
        css += '}';
        el.textContent = css;

        document.getElementById('currentSidebarBg').textContent = values.sidebar_bg || 'default';
        document.getElementById('currentNavbarBg').textContent = values.navbar_bg || 'default';
    }

    document.querySelectorAll('.btn-apply-theme').forEach(function(btn){
        btn.addEventListener('click', function(){
            const key = this.getAttribute('data-theme');
            const theme = THEMES[key] || THEMES.default;
            const label = key.charAt(0).toUpperCase() + key.slice(1);
            Swal.fire({
                title: `Apply ${label} theme?`,
                text: key === 'default' ? 'This will restore system default colors.' : 'This will update sidebar and navbar colors.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, apply',
            }).then(function(res){
                if(!res.isConfirmed) return;
                // Optimistic preview
                applyPreview(theme);
                // Persist
                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        sidebar_bg: theme.sidebar_bg,
                        navbar_bg: theme.navbar_bg
                    })
                }).then(async (r)=>{
                    if(!r.ok){ throw new Error((await r.json()).message || 'Failed to save'); }
                    return r.json();
                }).then(function(){
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        timer: 1500,
                        showConfirmButton: false,
                        timerProgressBar: true,
                    }).then(function(){
                        window.location.reload();
                    });
                }).catch(function(err){
                    Swal.fire({icon:'error', title:'Failed', text: err.message || 'Unable to save theme'});
                });
            });
        });
    });
})();
</script>
@stop