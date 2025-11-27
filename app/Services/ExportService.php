<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Symfony\Component\HttpFoundation\Response;

class ExportService
{
    /**
     * Export data to a CSV file (Excel compatible).
     *
     * @param  iterable  $data
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToExcel(iterable $data, string $filename = 'export.xlsx')
    {
        $rows = $this->normalize($data);

        $tempFile = $this->writeCsv($rows);

        return ResponseFactory::download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export data to a PDF-like response.
     * If dompdf est disponible, il sera utilisé, sinon HTML brut.
     *
     * @param  array  $data
     * @param  string  $template
     */
    public function exportToPdf(array $data, string $template = 'exports.generic')
    {
        // S'assurer que les données sont passées dans le bon format pour la vue
        $viewData = ['data' => $data];
        
        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, $viewData);

            return $pdf->download('export.pdf');
        }

        $html = view($template, $viewData)->render();

        return ResponseFactory::make($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="export.html"',
        ]);
    }

    /**
     * Export data to XML.
     *
     * @param  array  $data
     * @param  string  $root
     * @param  string  $filename
     */
    public function exportToXml(array $data, string $root = 'items', string $filename = 'export.xml')
    {
        $xml = new \SimpleXMLElement(sprintf('<%s/>', $root));

        foreach ($data as $item) {
            $child = $xml->addChild('item');
            foreach (Arr::dot((array) $item) as $key => $value) {
                $child->addChild(str_replace('.', '_', $key), htmlspecialchars((string) $value));
            }
        }

        return ResponseFactory::make(
            $xml->asXML(),
            200,
            [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    /**
     * Normalise les données en tableau de tableaux.
     *
     * @param  iterable  $data
     */
    private function normalize(iterable $data): Collection
    {
        return collect($data)->map(function ($row) {
            if (is_array($row)) {
                return $row;
            }

            if (is_object($row)) {
                return (array) $row;
            }

            return ['value' => $row];
        });
    }

    /**
     * Écrit un CSV temporaire et retourne le chemin du fichier.
     *
     * @param  \Illuminate\Support\Collection  $rows
     */
    private function writeCsv(Collection $rows): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'export_').'.csv';
        $handle = fopen($tempFile, 'w+');

        if ($rows->isEmpty()) {
            fclose($handle);

            return $tempFile;
        }

        $headers = array_keys($rows->first());
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, Arr::only($row, $headers));
        }

        fclose($handle);

        return $tempFile;
    }
}








