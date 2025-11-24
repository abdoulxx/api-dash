<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService)
    {
    }

    public function importExcel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file',
        ]);

        $data = $this->importService->importFromExcel($validated['file']);

        return response()->json([
            'count' => count($data),
            'data' => $data,
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file',
        ]);

        $data = $this->importService->importFromCsv($validated['file']);

        return response()->json([
            'count' => count($data),
            'data' => $data,
        ]);
    }
}




