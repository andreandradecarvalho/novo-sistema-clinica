@extends('layouts.base')
@section('html-class', 'layout-menu-fixed layout-compact')
@section('body')
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        @include('partials.sidebar')
        <div class="layout-page">
            @include('partials.navbar')
            <div class="content-wrapper">
                <main id="main-content" class="container-xxl flex-grow-1 container-p-y">
                    @include('partials.feedback')
                    @yield('content')
                </main>
                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl d-flex flex-wrap justify-content-between py-4 gap-2"><span class="text-body-secondary">Precordis · Gestão da clínica</span><a href="{{ route('security') }}" class="footer-link">Segurança da conta</a></div>
                </footer>
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>
    <div class="layout-overlay layout-menu-toggle" data-layout-overlay></div>
</div>
@endsection
