<!doctype html>
<html lang="pt-BR" dir="ltr" class="@yield('html-class')" data-bs-theme="light" data-template="vertical-menu-template">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Precordis') · Precordis</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <a href="#main-content" class="visually-hidden-focusable skip-link">Pular para o conteúdo</a>
    @yield('body')
</body>
</html>
