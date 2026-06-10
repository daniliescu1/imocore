<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class SetariController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setari/Index', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'auditLogs' => AuditLog::query()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'actiune' => $log->actiune,
                    'camp' => $log->camp ?: '—',
                    'valoare_veche' => $log->valoare_veche ?: '—',
                    'valoare_noua' => $log->valoare_noua ?: '—',
                    'motiv' => $log->motiv ?: '—',
                    'user_name' => $log->user_name,
                    'created_at' => $log->created_at->format('d.m.Y H:i'),
                ]),
        ]);
    }
}
