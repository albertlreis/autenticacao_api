<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MonitoramentoController extends Controller
{
    public function index(): JsonResponse
    {
//        if (!Gate::allows('monitoramento.visualizar')) {
//            return response()->json(['error' => 'Não autorizado.'], 403);
//        }

        $logs = DB::table('logs_metricas')
            ->orderByDesc('criado_em')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }
}
