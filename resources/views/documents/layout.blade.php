<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 18mm 14mm; }
        body {
            margin: 0;
            color: #1f2a2e;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        h2 {
            margin: 0;
            color: #00553f;
            font-size: 22px;
            letter-spacing: 0.02em;
        }
        p { margin: 4px 0 0; }
        .muted { color: #6f6f6f; font-weight: 700; }
        .doc-header {
            border-bottom: 2px solid #00553f;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .doc-header-row {
            width: 100%;
            border-collapse: collapse;
        }
        .doc-header-row td { vertical-align: top; }
        .meta-box {
            border: 1px solid #d9d0c4;
            border-radius: 8px;
            padding: 10px 12px;
            background: #faf8f4;
        }
        .meta-box span {
            display: block;
            color: #6f6f6f;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .meta-box strong { display: block; margin-top: 4px; font-size: 11px; }
        .parties-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -10px 16px;
        }
        .party-card {
            border: 1px solid #e9e2d8;
            border-radius: 10px;
            padding: 12px;
            vertical-align: top;
        }
        .party-heading {
            display: block;
            color: #6f6f6f;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .party-name {
            display: block;
            margin: 6px 0 8px;
            font-size: 13px;
        }
        .party-detail { margin: 0 0 4px; }
        .party-detail span {
            display: inline-block;
            min-width: 58px;
            color: #6f6f6f;
            font-weight: 700;
        }
        .annex-parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .annex-parties td {
            width: 20%;
            border: 1px solid #e9e2d8;
            padding: 10px;
            vertical-align: top;
        }
        .annex-parties span {
            display: block;
            color: #6f6f6f;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .annex-parties strong {
            display: block;
            margin-top: 4px;
            font-size: 11px;
        }
        .annex-parties small {
            display: block;
            margin-top: 3px;
            color: #6f6f6f;
            font-size: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data-table th,
        .data-table td {
            border-bottom: 1px solid #ece6db;
            padding: 7px 5px;
            font-size: 10px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .data-table th {
            background: #f4f4ee;
            color: #285948;
            font-weight: 800;
            text-align: left;
        }
        .section-header th {
            background: #edf8f2;
            color: #00553f;
        }
        .section-total td {
            border-top: 1px solid #e9e2d8;
            border-bottom: 2px solid #00553f;
            color: #00553f;
            font-weight: 800;
        }
        .totals-box {
            width: 280px;
            margin-left: auto;
            margin-top: 14px;
        }
        .totals-row {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-row td {
            padding: 4px 0;
            font-weight: 700;
        }
        .totals-row td:last-child { text-align: right; }
        .totals-grand td {
            padding: 8px 12px;
            border-radius: 999px;
            background: #1e88e5;
            color: #fff;
            font-weight: 800;
        }
        .footer-block { margin-top: 18px; padding-top: 14px; border-top: 1px solid #e9e2d8; }
        .footer-block p { margin: 0 0 8px; font-size: 10px; font-weight: 600; }
        .attached-annex { margin-top: 24px; padding-top: 18px; border-top: 1px solid #e9e2d8; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
