<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 10mm 10mm;
            size: A4 portrait;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #1f2a2e;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.35;
        }

        p { margin: 0; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border-bottom: 1px solid #ece6db;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f4f4ee;
            color: #285948;
            font-size: 13px;
            font-weight: 800;
        }

        td {
            color: #1f2a2e;
            font-size: 14px;
            font-weight: 600;
        }

        tr:last-child td { border-bottom: 0; }

        .generated-annex {
            width: 100%;
            border: 1px solid #e9e2d8;
            border-radius: 18px;
            padding: 34px;
            background: #fff;
        }

        .generated-annex-header {
            border-bottom: 2px solid #00553f;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .generated-annex-header h2 {
            margin: 0;
            color: #00553f;
            font-size: 30px;
            letter-spacing: 0.02em;
        }

        .generated-annex-header p {
            margin: 6px 0 0;
            color: #6f6f6f;
            font-weight: 700;
        }

        .invoice-document-header {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .invoice-document-header > div {
            display: table-cell;
            vertical-align: top;
        }

        .invoice-document-header > div:first-child {
            width: 33%;
        }

        .invoice-document-header-balance {
            width: 33%;
        }

        .invoice-number {
            margin: 8px 0 0;
            color: #1e88e5;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .invoice-period-note {
            margin-top: 6px;
            color: #6f6f6f;
            font-size: 13px;
            font-weight: 600;
        }

        .generated-annex-meta {
            border: 1px solid #e9e2d8;
            border-radius: 12px;
            padding: 14px 16px;
            background: #fffdf8;
        }

        .invoice-dates-meta {
            width: 34%;
            min-width: 240px;
        }

        .invoice-dates-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-dates-table tr {
            vertical-align: baseline;
        }

        .invoice-dates-table td {
            border: 0;
            padding: 0 0 6px;
            background: transparent;
            height: auto;
            color: #1f2a2e;
            font-weight: 700;
            vertical-align: baseline;
        }

        .invoice-dates-table tr:last-child td {
            padding-bottom: 0;
        }

        .invoice-date-label {
            width: 1%;
            white-space: nowrap;
            padding-right: 16px;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .invoice-date-value {
            width: 99%;
            text-align: right;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 800;
        }

        .invoice-date-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            color: #1f2a2e;
            font-size: 14px;
            font-weight: 700;
        }

        .invoice-date-row:last-child { margin-bottom: 0; }

        .invoice-date-row span,
        .invoice-date-row strong {
            display: table-cell;
            vertical-align: baseline;
        }

        .invoice-date-row span {
            width: 55%;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .invoice-date-row strong {
            font-size: 14px;
            font-weight: 800;
            text-align: right;
        }

        .generated-annex-header-centered-meta {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .generated-annex-header-centered-meta > div {
            display: table-cell;
            vertical-align: top;
        }

        .generated-annex-header-centered-meta > div:first-child {
            width: 33%;
        }

        .generated-annex-header-centered-meta .generated-annex-meta {
            width: 34%;
            text-align: center;
        }

        .generated-annex-header-balance {
            width: 33%;
        }

        .generated-annex-meta span,
        .generated-annex-parties span,
        .generated-annex-parties-email span {
            display: block;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .generated-annex-meta strong,
        .generated-annex-parties strong,
        .generated-annex-parties-email strong {
            display: block;
            margin-top: 4px;
            color: #1f2a2e;
            font-size: 16px;
            font-weight: 800;
        }

        .generated-annex-meta small,
        .generated-annex-parties small {
            display: block;
            margin-top: 3px;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 600;
        }

        .invoice-parties-grid {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-spacing: 18px 0;
        }

        .invoice-party-card {
            display: table-cell;
            width: 50%;
            padding: 18px 20px;
            border: 1px solid #e9e2d8;
            border-radius: 14px;
            background: #fff;
            vertical-align: top;
        }

        .invoice-party-heading {
            display: block;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .invoice-party-name {
            display: block;
            margin: 2px 0 8px;
            color: #1f2a2e;
            font-size: 22px;
            line-height: 1.15;
            font-weight: 900;
        }

        .invoice-party-detail {
            margin: 0 0 4px;
            color: #1f2a2e;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 600;
        }

        .invoice-party-detail span {
            display: inline-block;
            min-width: 72px;
            margin-right: 8px;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 800;
        }

        .generated-annex-parties {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-spacing: 12px 0;
        }

        .generated-annex-parties > div {
            display: table-cell;
            width: 20%;
            padding: 13px 14px;
            border: 1px solid #e9e2d8;
            border-radius: 12px;
            background: #fff;
            vertical-align: top;
        }

        .generated-annex-table {
            width: 100%;
            table-layout: fixed;
        }

        .generated-annex-table th,
        .generated-annex-table td {
            padding: 12px 9px;
            font-size: 12px;
            white-space: normal;
            word-wrap: break-word;
        }

        .generated-annex-table th:nth-child(1),
        .generated-annex-table td:nth-child(1) { width: 58px; }

        .generated-annex-table th:nth-child(2),
        .generated-annex-table td:nth-child(2) { width: 31%; }

        .generated-annex-table th:nth-child(3),
        .generated-annex-table td:nth-child(3),
        .generated-annex-table th:nth-child(4),
        .generated-annex-table td:nth-child(4),
        .generated-annex-table th:nth-child(5),
        .generated-annex-table td:nth-child(5),
        .generated-annex-table th:nth-child(6),
        .generated-annex-table td:nth-child(6),
        .generated-annex-table th:nth-child(7),
        .generated-annex-table td:nth-child(7),
        .generated-annex-table th:nth-child(8),
        .generated-annex-table td:nth-child(8),
        .generated-annex-table th:nth-child(9),
        .generated-annex-table td:nth-child(9) { width: 9%; }

        .generated-annex-section-header th {
            background: #f4f4ee;
            color: #285948;
            font-size: 13px;
            font-weight: 800;
            padding: 10px 12px;
            border-bottom: 1px solid #e9e2d8;
        }

        .generated-annex-section-total td {
            border-top: 1px solid #e9e2d8;
            border-bottom: 2px solid #00553f;
            color: #00553f;
            font-size: 13px;
            font-weight: 900;
        }

        .invoice-totals-summary {
            width: 360px;
            margin: 20px 0 0 auto;
        }

        .invoice-totals-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
            color: #1f2a2e;
            font-size: 14px;
            font-weight: 700;
        }

        .invoice-totals-row span,
        .invoice-totals-row strong {
            display: table-cell;
            vertical-align: middle;
        }

        .invoice-totals-row strong {
            text-align: right;
            font-size: 15px;
            font-weight: 800;
        }

        .invoice-totals-grand-total {
            margin-top: 4px;
            padding: 12px 16px;
            border-radius: 999px;
            background: #1e88e5;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
        }

        .invoice-totals-grand-total span,
        .invoice-totals-grand-total strong {
            color: #fff;
            font-size: 16px;
        }

        .invoice-payment-footer {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e9e2d8;
        }

        .invoice-payment-instructions {
            margin-bottom: 18px;
        }

        .invoice-payment-instructions strong {
            display: block;
            margin-bottom: 6px;
            color: #1f2a2e;
            font-size: 14px;
            font-weight: 800;
        }

        .invoice-payment-instructions p {
            margin: 0;
            color: #1f2a2e;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .invoice-legal-notes p {
            margin: 0 0 12px;
            color: #1f2a2e;
            font-size: 12px;
            line-height: 1.55;
            font-weight: 600;
        }

        .invoice-legal-notes p:last-child { margin-bottom: 0; }

        .invoice-payment-summary {
            margin: 22px 0 0;
            padding-top: 16px;
            border-top: 1px solid #e9e2d8;
            color: #6f6f6f;
            font-size: 12px;
            font-weight: 700;
        }

        .invoice-attached-annex {
            margin-top: 0;
            padding-top: 0;
            border-top: 0;
        }

        .generated-annex-parties-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 10px 0;
        }

        .generated-annex-parties-table td {
            width: 20%;
            padding: 10px 12px;
            border: 1px solid #e9e2d8;
            border-radius: 10px;
            background: #fffdf8;
            vertical-align: top;
            height: auto;
        }

        .generated-annex-parties-table td[colspan] {
            width: 100%;
        }

        .generated-annex-parties-table td[colspan] strong {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .generated-annex-parties-table span {
            display: block;
            color: #6f6f6f;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .generated-annex-parties-table strong {
            display: block;
            margin-top: 4px;
            color: #1f2a2e;
            font-size: 11px;
            font-weight: 800;
        }

        .generated-annex-parties-table small {
            display: block;
            margin-top: 3px;
            color: #6f6f6f;
            font-size: 9px;
            font-weight: 600;
            line-height: 1.3;
        }

        .pdf-annex-page .generated-annex-header-centered-meta {
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        .pdf-annex-page .generated-annex-header h2 {
            font-size: 20px;
        }

        .pdf-invoice-document {
            padding: 0;
            border: 0;
            border-radius: 0;
            background: #fff;
        }

        .pdf-invoice-pro {
            border-top: 4px solid #00553f;
        }

        .pdf-invoice-with-annex .pdf-annex-page {
            page-break-before: always;
        }

        .pdf-invoice-page {
            padding: 14px 16px 12px;
        }

        .pdf-invoice-kicker {
            margin: 0 0 4px;
            color: #6f6f6f;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .pdf-invoice-page .generated-annex-header {
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .pdf-invoice-page .generated-annex-header h2 {
            font-size: 24px;
            letter-spacing: 0.06em;
        }

        .pdf-invoice-page .invoice-number {
            margin-top: 4px;
            font-size: 20px;
        }

        .pdf-invoice-page .invoice-period-note {
            margin-top: 4px;
            font-size: 10px;
        }

        .pdf-invoice-page .generated-annex-meta {
            padding: 10px 12px;
            border-radius: 10px;
            background: #fffdf8;
        }

        .pdf-invoice-page .invoice-dates-meta {
            min-width: 220px;
        }

        .pdf-invoice-page .invoice-date-label {
            padding-right: 12px;
            font-size: 9px;
        }

        .pdf-invoice-page .invoice-date-value {
            font-size: 11px;
        }

        .pdf-invoice-page .invoice-dates-table td {
            padding-bottom: 4px;
        }

        .invoice-parties-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 12px 0;
        }

        .invoice-parties-table td {
            border: 0;
            padding: 0;
            background: transparent;
            height: auto;
            vertical-align: top;
        }

        .pdf-invoice-page .invoice-party-card {
            padding: 10px 12px;
            border: 1px solid #e9e2d8;
            border-radius: 10px;
            background: #fffdf8;
        }

        .pdf-invoice-page .invoice-party-heading {
            margin-bottom: 4px;
            font-size: 9px;
            letter-spacing: 0.08em;
        }

        .pdf-invoice-page .invoice-party-name {
            margin: 0 0 6px;
            font-size: 14px;
        }

        .pdf-invoice-page .invoice-party-detail {
            margin-bottom: 2px;
            font-size: 9.5px;
            line-height: 1.3;
        }

        .pdf-invoice-page .invoice-party-detail span {
            min-width: 56px;
            margin-right: 6px;
            font-size: 9px;
        }

        .col-numeric {
            text-align: right;
            white-space: nowrap;
        }

        .pdf-invoice-lines-table {
            margin-top: 2px;
        }

        .pdf-invoice-lines-table thead th {
            background: #edf8f2;
            color: #00553f;
            border-bottom: 2px solid #00553f;
            font-size: 9px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .pdf-invoice-page .generated-annex-table th,
        .pdf-invoice-page .generated-annex-table td {
            padding: 6px 5px;
            font-size: 9.5px;
        }

        .pdf-invoice-page .generated-annex-table tbody tr:nth-child(even) td {
            background: #fcfaf6;
        }

        .invoice-totals-panel {
            width: 290px;
            margin: 10px 0 0 auto;
            border: 1px solid #e9e2d8;
            border-radius: 10px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }

        .invoice-totals-panel td {
            border: 0;
            border-bottom: 1px solid #ece6db;
            padding: 7px 10px;
            background: #fff;
            height: auto;
            font-size: 10px;
            font-weight: 700;
            vertical-align: middle;
        }

        .invoice-totals-panel tr:last-child td {
            border-bottom: 0;
        }

        .invoice-totals-grand-row td {
            background: #1e88e5;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
        }

        .pdf-invoice-page .invoice-payment-footer {
            margin-top: 10px;
            padding-top: 0;
            border-top: 0;
        }

        .invoice-footer-grid {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: separate;
            border-spacing: 10px 0;
        }

        .invoice-footer-grid td {
            border: 0;
            padding: 0;
            background: transparent;
            height: auto;
            vertical-align: top;
        }

        .invoice-payment-panel,
        .invoice-payment-summary-panel {
            padding: 10px 12px;
            border: 1px solid #e9e2d8;
            border-radius: 10px;
            background: #fffdf8;
        }

        .invoice-payment-panel strong,
        .invoice-payment-summary-panel strong {
            display: block;
            margin-bottom: 4px;
            color: #00553f;
            font-size: 10px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .invoice-payment-panel p,
        .invoice-payment-summary-panel p {
            margin: 0 0 2px;
            font-size: 9.5px;
            line-height: 1.35;
            font-weight: 700;
        }

        .invoice-payment-summary-panel {
            background: #edf8f2;
            border-color: #cfe7db;
        }

        .invoice-legal-panel {
            padding: 8px 10px;
            border: 1px solid #ece6db;
            border-radius: 8px;
            background: #faf8f4;
        }

        .invoice-legal-panel p {
            margin: 0 0 4px;
            color: #6f6f6f;
            font-size: 7.8px;
            line-height: 1.35;
            font-weight: 600;
        }

        .invoice-legal-panel p:last-child {
            margin-bottom: 0;
        }

        .pdf-annex-page {
            padding: 14px 16px 12px;
            border-top: 4px solid #00553f;
        }

        .pdf-annex-page .compact-annex-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        .pdf-annex-page .compact-annex-header h2 {
            font-size: 20px;
        }

        .compact-annex-header {
            margin-bottom: 16px;
            padding-bottom: 12px;
        }

        .compact-annex-header h2 {
            font-size: 22px;
        }

        .pdf-annex-standalone {
            padding: 0;
            border: 0;
            border-radius: 0;
            background: #fff;
        }

        .pdf-annex-page .generated-annex-header {
            border-bottom: 2px solid #00553f;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .pdf-annex-page .generated-annex-header p {
            margin-top: 4px;
            font-size: 10px;
        }

        .pdf-annex-page .generated-annex-meta {
            padding: 10px 12px;
            border-radius: 10px;
            background: #fffdf8;
            border: 1px solid #e9e2d8;
        }

        .pdf-annex-page .generated-annex-meta span {
            font-size: 9px;
            letter-spacing: 0.08em;
        }

        .pdf-annex-page .generated-annex-meta strong {
            margin-top: 4px;
            font-size: 11px;
        }

        .pdf-annex-page .generated-annex-parties-table {
            margin-bottom: 12px;
            border-spacing: 8px 0;
        }

        .pdf-annex-page .generated-annex-parties-table td {
            padding: 10px 11px;
            border-radius: 10px;
            background: #fffdf8;
        }

        .pdf-annex-page .generated-annex-table th,
        .pdf-annex-page .generated-annex-table td {
            padding: 6px 5px;
            font-size: 9.5px;
        }

        .pdf-annex-page .generated-annex-table tbody tr:nth-child(even) td {
            background: #fcfaf6;
        }

        .pdf-annex-page .generated-annex-section-header th {
            background: #edf8f2;
            color: #00553f;
            border-bottom: 2px solid #00553f;
            font-size: 9px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .pdf-annex-page .generated-annex-section-total td {
            background: #f7fbf9;
            font-size: 10px;
        }

        .pdf-annex-page .annex-totals-panel {
            margin-top: 12px;
        }

        .pdf-annex-footer-note {
            margin: 10px 0 0;
            padding-top: 8px;
            border-top: 1px solid #ece6db;
            color: #6f6f6f;
            font-size: 8px;
            font-weight: 600;
            text-align: right;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
