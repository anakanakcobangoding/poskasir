<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Struk') · {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
    <style>
        @page {
            margin: 8mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff;
            }

            .receipt {
                box-shadow: none;
                border: 0;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 py-6 font-sans text-slate-800 antialiased">
    @yield('content')
</body>
</html>
