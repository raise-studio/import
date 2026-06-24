<?php

namespace RaiseStudio\Import\Tests\Feature\Pro;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RaiseStudio\Import\Enums\ImportStatus;
use RaiseStudio\Import\Pro\Models\ImportLog;
use RaiseStudio\Import\Tests\Stubs\TestUser;
use RaiseStudio\Import\Tests\TestCase;

class ImportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fake a logged-in user for auth-protected routes
        $user = Mockery::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthPassword')->andReturn('');
        $user->shouldReceive('getRememberToken')->andReturn('');
        $user->shouldReceive('setRememberToken')->andReturn(null);
        $user->shouldReceive('getRememberTokenName')->andReturn('remember_token');
        $this->actingAs($user);

        // Use fake local disk for uploaded files
        Storage::fake('local');
    }

    /** @test */
    public function upload_endpoint_returns_headers_and_preview()
    {
        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,phone\nAlice,alice@test.com,13800138000\nBob,bob@test.com,13900139000\n"
        );

        $response = $this->post(route('raise-import.upload'), [
            'file' => $file,
            'model_class' => TestUser::class,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'path',
            'headers',
            'preview',
            'total_rows',
        ]);

        $data = $response->json();
        $this->assertEquals(['name', 'email', 'phone'], $data['headers']);
        $this->assertEquals(2, $data['total_rows']);
        $this->assertCount(2, $data['preview']);
    }

    /** @test */
    public function upload_validates_file_required()
    {
        $response = $this->post(route('raise-import.upload'), [
            'model_class' => TestUser::class,
        ]);

        $response->assertStatus(302); // validation redirect
        $response->assertSessionHasErrors('file');
    }

    /** @test */
    public function upload_validates_model_class_required()
    {
        $file = UploadedFile::fake()->create('users.csv', 100);

        $response = $this->post(route('raise-import.upload'), [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('model_class');
    }

    /** @test */
    public function preview_endpoint_returns_mapped_rows()
    {
        // First upload the file
        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,phone\nAlice,alice@test.com,13800138000\nBob,bob@test.com,13900139000\n"
        );

        $uploadResponse = $this->post(route('raise-import.upload'), [
            'file' => $file,
            'model_class' => TestUser::class,
        ]);

        $path = $uploadResponse->json('path');

        // Preview with mapping
        $response = $this->post(route('raise-import.preview'), [
            'path' => $path,
            'mapping' => ['name' => 'name', 'email' => 'email'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'rows',
            'total',
        ]);

        $data = $response->json();
        $this->assertEquals(2, $data['total']);
        $this->assertCount(2, $data['rows']);
        $this->assertEquals('Alice', $data['rows'][0]['name']);
    }

    /** @test */
    public function import_endpoint_executes_import_and_returns_result()
    {
        // Upload first
        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email\nAlice,alice@test.com\nBob,bob@test.com\n"
        );

        $uploadResponse = $this->post(route('raise-import.upload'), [
            'file' => $file,
            'model_class' => TestUser::class,
        ]);

        $path = $uploadResponse->json('path');

        // Execute import
        $response = $this->post(route('raise-import.import'), [
            'path' => $path,
            'model_class' => TestUser::class,
            'mapping' => ['name' => 'name', 'email' => 'email'],
            'unique_by' => [],
            'on_duplicate' => 'skip',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'imported' => 2,
            'skipped' => 0,
            'failed' => 0,
        ]);

        // Verify data was actually imported
        $this->assertDatabaseHas('test_users', ['email' => 'alice@test.com']);
        $this->assertDatabaseHas('test_users', ['email' => 'bob@test.com']);

        // Verify import log was created
        $this->assertDatabaseHas('import_logs', [
            'model_class' => TestUser::class,
            'imported_count' => 2,
            'status' => ImportStatus::Completed->value,
        ]);
    }

    /** @test */
    public function import_returns_partial_status_when_some_rows_fail()
    {
        // A file where the second row will fail (missing name is not required in TestUser)
        // Let's create a scenario with a duplicate
        TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);

        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email\nAlice,alice@test.com\nBob,bob@test.com\n"
        );

        $uploadResponse = $this->post(route('raise-import.upload'), [
            'file' => $file,
            'model_class' => TestUser::class,
        ]);

        $path = $uploadResponse->json('path');

        $response = $this->post(route('raise-import.import'), [
            'path' => $path,
            'model_class' => TestUser::class,
            'mapping' => ['name' => 'name', 'email' => 'email'],
            'unique_by' => ['email'],
            'on_duplicate' => 'error', // Will error on duplicate email
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertGreaterThan(0, $data['failed']);
    }

    /** @test */
    public function template_endpoint_downloads_csv()
    {
        $response = $this->get(route('raise-import.template', [
            'modelClass' => str_replace('\\', '_', TestUser::class),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="import-template.csv"');

        // CSV content type includes charset in some Laravel versions
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringStartsWith('text/csv', $contentType);

        // Verify content contains expected headers
        $content = $response->getContent();
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Email', $content);
    }

    /** @test */
    public function template_returns_404_for_nonexistent_model()
    {
        $response = $this->get(route('raise-import.template', [
            'modelClass' => 'Nonexistent_Model',
        ]));

        $response->assertStatus(404);
    }

    /** @test */
    public function download_errors_returns_csv()
    {
        $errors = [
            ['row' => 1, 'field' => 'email', 'value' => 'bad', 'error' => 'Invalid email format'],
            ['row' => 2, 'field' => 'name', 'value' => '', 'error' => 'Field is required'],
        ];

        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => '/tmp/test.csv',
            'total_rows' => 2,
            'imported_count' => 0,
            'failed_count' => 2,
            'status' => ImportStatus::Failed,
            'errors' => $errors,
        ]);

        $response = $this->get(route('raise-import.errors.download', [
            'importLog' => $importLog->id,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', "attachment; filename=\"import-errors-{$importLog->id}.csv\"");

        // CSV content type includes charset in some Laravel versions
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringStartsWith('text/csv', $contentType);

        // Verify error report content
        $content = $response->getContent();
        $this->assertStringContainsString('row,field,value,error', $content);
        $this->assertStringContainsString('Invalid email format', $content);
        $this->assertStringContainsString('Field is required', $content);
    }

    /** @test */
    public function download_errors_returns_404_when_no_errors()
    {
        $importLog = ImportLog::create([
            'model_class' => TestUser::class,
            'file_name' => 'test.csv',
            'file_path' => '/tmp/test.csv',
            'status' => ImportStatus::Completed,
        ]);

        $response = $this->get(route('raise-import.errors.download', [
            'importLog' => $importLog->id,
        ]));

        $response->assertStatus(404);
    }

    /** @test */
    public function import_validates_required_fields()
    {
        $response = $this->post(route('raise-import.import'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['path', 'model_class', 'mapping']);
    }
}
