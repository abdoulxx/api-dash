<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_export_excel_returns_download(): void
    {
        $response = $this->postJson('/api/export/excel', [
            'data' => [
                ['col1' => 'value1', 'col2' => 'value2'],
            ],
            'filename' => 'test.xlsx',
        ]);

        $response->assertOk();
        $this->assertTrue($response->headers->has('content-disposition'));
    }

    public function test_import_csv_returns_parsed_data(): void
    {
        $csvContent = "name,amount\nJohn,10\nJane,20\n";

        $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

        $response = $this->postJson('/api/import/csv', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['count' => 2])
            ->assertJsonFragment(['name' => 'John']);
    }
}





