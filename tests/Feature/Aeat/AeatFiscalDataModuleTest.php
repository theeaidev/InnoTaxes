<?php

namespace Tests\Feature\Aeat;

use App\Jobs\ProcessAeatFiscalDataRequest;
use App\Models\AeatCertificateProfile;
use App\Models\AeatFiscalDataRequest;
use App\Models\User;
use App\Services\Aeat\AeatFiscalDataParser;
use App\Services\Aeat\AeatFiscalDataRequestProcessor;
use App\Services\Aeat\AeatHttpClient;
use App\Services\Aeat\AeatIntegrationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AeatFiscalDataModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_private_aeat_module(): void
    {
        $this->get(route('aeat.fiscal-data.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_private_aeat_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('aeat.fiscal-data.index'))
            ->assertOk()
            ->assertSee('AEAT Fiscal Data')
            ->assertSee('Secure Certificate Profiles');
    }

    public function test_user_can_store_an_encrypted_certificate_profile(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $certificateContents = $this->makePkcs12Certificate('secret-passphrase');

        $response = $this->actingAs($user)->post(route('aeat.fiscal-data.certificate-profiles.store'), [
            'name' => 'Main AEAT Certificate',
            'certificate_format' => 'p12',
            'passphrase' => 'secret-passphrase',
            'certificate_file' => UploadedFile::fake()->createWithContent('certificate.p12', $certificateContents),
        ]);

        $response->assertRedirect(route('aeat.fiscal-data.index'));

        $profile = AeatCertificateProfile::query()->firstOrFail();

        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame('Main AEAT Certificate', $profile->name);
        Storage::disk('local')->assertExists($profile->certificate_path);
        $this->assertNotSame($certificateContents, Storage::disk('local')->get($profile->certificate_path));
    }

    public function test_invalid_pkcs12_certificate_is_rejected_before_storing_the_profile(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->from(route('aeat.fiscal-data.index'))
            ->actingAs($user)
            ->post(route('aeat.fiscal-data.certificate-profiles.store'), [
                'name' => 'Broken certificate',
                'certificate_format' => 'p12',
                'passphrase' => 'wrong-passphrase',
                'certificate_file' => UploadedFile::fake()->createWithContent('certificate.p12', 'not-a-valid-pkcs12'),
            ]);

        $response->assertRedirect(route('aeat.fiscal-data.index'));
        $response->assertSessionHasErrors('certificate_file');
        $this->assertDatabaseCount('aeat_certificate_profiles', 0);
    }

    public function test_reference_request_is_queued_inside_the_existing_private_area(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('aeat.fiscal-data.requests.store'), [
            'auth_method' => 'reference',
            'taxpayer_nif' => '12345678Z',
            'auth_nif' => '12345678Z',
            'pdp' => 'S',
            'reference_code' => 'ABC123',
        ]);

        $request = AeatFiscalDataRequest::query()->firstOrFail();

        $response->assertRedirect(route('aeat.fiscal-data.index', ['request' => $request->id]));
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame('reference', $request->auth_method);
        $this->assertTrue($request->pdp);
        $this->assertSame(hash('sha512', 'ABC123'), $request->session_state['reference_hash']);
        $this->assertArrayNotHasKey('reference_code', $request->payload ?? []);

        Queue::assertPushed(ProcessAeatFiscalDataRequest::class, function (ProcessAeatFiscalDataRequest $job) use ($request): bool {
            return $job->requestId === $request->id;
        });
    }

    public function test_processor_records_failures_even_with_invalid_utf8_context(): void
    {
        $request = User::factory()->create()->aeatFiscalDataRequests()->create([
            'status' => 'processing',
            'stage' => 'ratification_check',
            'auth_method' => 'certificate',
            'taxpayer_nif' => '12345678Z',
            'pdp' => true,
        ]);

        $processor = new AeatFiscalDataRequestProcessor(
            $this->createMock(AeatHttpClient::class),
            $this->createMock(AeatFiscalDataParser::class),
        );

        $processor->recordFailure(
            $request->id,
            new AeatIntegrationException(
                message: 'AEAT returned invalid bytes.',
                stage: 'certificate_download',
                context: ['body' => 'prefix '.hex2bin('B1')],
                retryable: false,
            ),
            3,
            3,
            false,
        );

        $request->refresh();

        $this->assertSame('failed', $request->status);
        $this->assertSame('certificate_download', $request->stage);
        $this->assertNotNull($request->completed_at);
        $this->assertIsArray($request->last_error_context);
        $this->assertNotFalse(json_encode($request->last_error_context));
        $this->assertDatabaseCount('aeat_fiscal_data_errors', 1);
    }

    public function test_processor_exposes_a_clear_error_before_the_official_release_date(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-03-06 12:00:00');
        config([
            'aeat.exercise' => '2025',
            'aeat.release_date' => '2026-03-19',
        ]);

        $request = User::factory()->create()->aeatFiscalDataRequests()->create([
            'status' => 'queued',
            'stage' => 'queued',
            'auth_method' => 'reference',
            'taxpayer_nif' => '12345678Z',
            'auth_nif' => '12345678Z',
            'pdp' => true,
            'session_state' => ['reference_hash' => hash('sha512', 'ABC123')],
        ]);

        $processor = new AeatFiscalDataRequestProcessor(
            $this->createMock(AeatHttpClient::class),
            $this->createMock(AeatFiscalDataParser::class),
        );

        try {
            $processor->process($request->id, 1, 3);
            $this->fail('The processor should block downloads before the official release date.');
        } catch (AeatIntegrationException $exception) {
            $processor->recordFailure($request->id, $exception, 1, 3, false);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }

        $request->refresh();

        $this->assertSame('failed', $request->status);
        $this->assertSame('service_unavailable', $request->stage);
        $this->assertSame('unknown', $request->domicile_status);
        $this->assertStringContainsString('19/03/2026', (string) $request->last_error_message);
    }

    public function test_user_cannot_download_another_users_private_aeat_file(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $request = $owner->aeatFiscalDataRequests()->create([
            'status' => 'completed',
            'stage' => 'completed',
            'auth_method' => 'certificate',
            'taxpayer_nif' => '12345678Z',
            'pdp' => true,
        ]);

        $file = $request->files()->create([
            'disk' => 'local',
            'path' => 'private/aeat/files/'.$owner->id.'/sample.txt',
            'filename' => 'sample.txt',
            'sha256' => hash('sha256', 'sample'),
            'bytes' => 6,
            'line_count' => 1,
            'record_count' => 1,
        ]);

        $this->actingAs($intruder)
            ->get(route('aeat.fiscal-data.files.download', $file))
            ->assertForbidden();
    }

    public function test_authenticated_user_can_refresh_the_request_panels_without_a_full_page_reload(): void
    {
        $user = User::factory()->create();
        $request = $user->aeatFiscalDataRequests()->create([
            'status' => 'queued',
            'stage' => 'queued',
            'auth_method' => 'reference',
            'taxpayer_nif' => '12345678Z',
            'auth_nif' => '12345678Z',
            'pdp' => true,
            'domicile_status' => 'unknown',
        ]);

        $this->actingAs($user)
            ->get(route('aeat.fiscal-data.request-panels', ['request' => $request->id]))
            ->assertOk()
            ->assertSee('Request History')
            ->assertSee('Request Detail')
            ->assertSee('data-has-active-requests="1"', false)
            ->assertSee("Request #{$request->id} for {$request->taxpayer_nif}");
    }

    public function test_user_cannot_fetch_another_users_request_detail_through_the_request_panels_endpoint(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $request = $owner->aeatFiscalDataRequests()->create([
            'status' => 'failed',
            'stage' => 'service_unavailable',
            'auth_method' => 'certificate',
            'taxpayer_nif' => '12345678Z',
            'pdp' => true,
            'domicile_status' => 'ratified',
        ]);

        $this->actingAs($intruder)
            ->get(route('aeat.fiscal-data.request-panels', ['request' => $request->id]))
            ->assertOk()
            ->assertSee('Select a request')
            ->assertDontSee("Request #{$request->id} for {$request->taxpayer_nif}");
    }

    protected function makePkcs12Certificate(string $passphrase): string
    {
        $opensslConfig = 'C:\xampp\apache\conf\openssl.cnf';
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
            'digest_alg' => 'sha256',
            'config' => $opensslConfig,
        ];

        $privateKey = openssl_pkey_new($options);
        $this->assertNotFalse($privateKey);

        $csr = openssl_csr_new([
            'commonName' => 'AEAT Test Certificate',
            'organizationName' => 'InnoTaxes MVP',
            'countryName' => 'ES',
        ], $privateKey, $options);
        $this->assertNotFalse($csr);

        $certificate = openssl_csr_sign($csr, null, $privateKey, 1, $options);
        $this->assertNotFalse($certificate);

        $exported = null;
        $this->assertTrue(openssl_pkcs12_export($certificate, $exported, $privateKey, $passphrase));
        $this->assertIsString($exported);
        $this->assertNotSame('', $exported);

        return $exported;
    }
}
