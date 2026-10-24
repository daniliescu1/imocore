<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 18mm 16mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #1f2a2e;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
        }
        h1 {
            margin: 0 0 6px;
            color: #00553f;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        h2 {
            margin: 18px 0 8px;
            color: #00553f;
            font-size: 14px;
            font-weight: 800;
            border-bottom: 2px solid #00553f;
            padding-bottom: 4px;
        }
        h3 {
            margin: 12px 0 6px;
            color: #285948;
            font-size: 11.5px;
            font-weight: 800;
        }
        p { margin: 0 0 8px; }
        ul, ol { margin: 0 0 8px 18px; padding: 0; }
        li { margin-bottom: 4px; }
        .meta {
            margin-bottom: 16px;
            padding: 10px 12px;
            border: 1px solid #e9e2d8;
            border-radius: 8px;
            background: #fffdf8;
        }
        .meta p { margin-bottom: 3px; font-size: 10px; }
        .meta strong { color: #00553f; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px;
            font-size: 9.5px;
        }
        th, td {
            border: 1px solid #e9e2d8;
            padding: 6px 7px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #edf8f2;
            color: #00553f;
            font-weight: 800;
        }
        .note {
            margin-top: 14px;
            padding: 8px 10px;
            border-left: 3px solid #e8a317;
            background: #fff9ee;
            font-size: 9px;
            color: #5c4a1f;
        }
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ece6db;
            font-size: 8.5px;
            color: #6f6f6f;
            text-align: center;
        }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
