<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exportService)
    {
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'filename' => 'nullable|string',
        ]);

        return $this->exportService->exportToExcel(
            $validated['data'],
            $validated['filename'] ?? 'export.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'template' => 'nullable|string',
        ]);

        return $this->exportService->exportToPdf(
            $validated['data'],
            $validated['template'] ?? 'exports.generic'
        );
    }

    public function exportXml(Request $request)
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'root' => 'nullable|string',
            'filename' => 'nullable|string',
        ]);

        return $this->exportService->exportToXml(
            $validated['data'],
            $validated['root'] ?? 'items',
            $validated['filename'] ?? 'export.xml'
        );
    }

    public function templates(): JsonResponse
    {
        return response()->json([
            'templates' => [
                'exports.generic',
            ],
        ]);
    }
}




