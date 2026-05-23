<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class AttachmentsExportTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
        Storage::fake('attachments');
        Storage::fake('exports');
    }

    public function test_upload_and_download_attachment_and_generate_export(): void
    {
        $merchant = $this->makeMerchant();

        $attachmentResponse = $this->postJson('/api/attachments', [
            'attachable_type' => 'merchants',
            'attachable_id' => $merchant->id,
            'category' => 'document',
            'notes' => 'Merchant contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'),
        ]);

        $attachmentId = $attachmentResponse->json('data.id');

        $attachmentResponse
            ->assertCreated()
            ->assertJsonPath('data.attachable_type', 'merchants');

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'attachable_type' => 'merchants',
            'attachable_id' => $merchant->id,
        ]);

        $downloadResponse = $this->get('/api/attachments/'.$attachmentId.'/download');
        $downloadResponse->assertOk();
        $this->assertStringContainsString('contract.pdf', $downloadResponse->headers->get('content-disposition', ''));

        $exportResponse = $this->postJson('/api/exports/audit_logs', [
            'format' => 'csv',
        ]);

        $exportId = $exportResponse->json('data.id');

        $exportResponse
            ->assertCreated()
            ->assertJsonPath('data.export_type', 'audit_logs')
            ->assertJsonPath('data.format', 'csv');

        $this->assertDatabaseHas('export_logs', [
            'id' => $exportId,
            'export_type' => 'audit_logs',
            'format' => 'csv',
            'status' => 'completed',
        ]);
    }
}
