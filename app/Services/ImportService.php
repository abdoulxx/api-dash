<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImportService
{
    /**
     * Import data from an Excel/CSV file.
     *
     * @return array<int, array<string, mixed>>
     */
    public function importFromExcel(UploadedFile $file): array
    {
        if (class_exists('\\Maatwebsite\\Excel\\Facades\\Excel')) {
            $collection = \Maatwebsite\Excel\Facades\Excel::toCollection(new class implements \Maatwebsite\Excel\Concerns\ToCollection {
                public $collection;
                public function collection(\Illuminate\Support\Collection $collection)
                {
                    $this->collection = $collection;
                }
            }, $file);

            return $collection?->first()?->toArray() ?? [];
        }

        return $this->parseCsv($file);
    }

    /**
     * Import data from a CSV file.
     *
     * @return array<int, array<string, mixed>>
     */
    public function importFromCsv(UploadedFile $file): array
    {
        return $this->parseCsv($file);
    }

    /**
     * Parse CSV fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [];
        }

        $rows = [];
        $headers = [];

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (empty($headers)) {
                $headers = $data;
                continue;
            }

            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }
}




