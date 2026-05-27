<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MonitoramentoController extends Controller
{
    public function index(): JsonResponse
    {
//        if (!Gate::allows('monitoramento.visualizar')) {
//            return response()->json(['error' => 'Nao autorizado.'], 403);
//        }

        $logs = DB::table('auditoria_logs')
            ->where('source_system', 'auth')
            ->where('categoria', 'metrica')
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $context = json_decode($row->context_json ?? '{}', true) ?: [];

                return [
                    'id' => (int) $row->id,
                    'chave' => $context['chave'] ?? $row->label,
                    'origem' => $context['origem'] ?? $row->acao,
                    'status' => $row->status,
                    'usuario_id' => $row->actor_id,
                    'duracao_ms' => $context['duracao_ms'] ?? null,
                    'criado_em' => $row->occurred_at,
                ];
            });

        return response()->json($logs);
    }
}
