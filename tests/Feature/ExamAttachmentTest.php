<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\DownloadExamAttachment;
use App\Domain\Catalog\Actions\UploadExamAttachment;
use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\Services\ExamAttachmentStorage;
use App\Domain\Catalog\ValueObjects\AttachmentContents;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Clinics;

function syntheticOrientation(string $name = 'orientacao.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6l8AAAAASUVORK5CYII=', true));
}

function syntheticOrientationContents(): AttachmentContents
{
    $file = syntheticOrientation();

    return new AttachmentContents(file_get_contents($file->getPathname()));
}

beforeEach(function (): void {
    $this->withoutVite();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    DB::beginTransaction();
    $this->seed();
    Storage::fake('local');
    $this->context = app(TenantContext::class);
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    $seed = function (string $suffix): array {
        $exam = ExamType::query()->create(['code' => 'SINT-'.$suffix, 'name' => 'Exame sintético '.$suffix]);
        $file = syntheticOrientationContents();
        $storage = app(ExamAttachmentStorage::class);
        $path = $storage->newPath((int) $exam->id, $file);
        $storage->put($path, $file);
        $attachment = new ExamAttachment(['label' => 'Orientação '.$suffix]);
        $attachment->examType()->associate($exam);
        $attachment->forceFill(['disk' => 'local', 'path' => $path])->save();

        return [$exam, $attachment];
    };
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    [$this->examA, $this->attachmentA] = $this->context->run($this->a['tenant'], fn () => $seed('A'));
    [$this->examB, $this->attachmentB] = $this->context->run($this->b['tenant'], fn () => $seed('B'));
    $this->otherA = $this->context->run($this->a['tenant'], fn () => ExamType::query()->create(['code' => 'OTHER', 'name' => 'Outro exame A']));
    $this->base = 'http://'.$this->a['host'].'/exam-types/'.$this->examA->public_id.'/attachments';
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('uploads encrypted private bytes and downloads them only through the authorized route', function (): void {
    $this->get($this->base.'/create')->assertOk()->assertSee('Novo anexo');
    $file = syntheticOrientation('nome-nao-publicado.png');
    $bytes = file_get_contents($file->getPathname());
    $this->post($this->base, ['label' => 'Orientação nova', 'file' => $file])->assertRedirect();
    $attachment = $this->context->run($this->a['tenant'], fn () => ExamAttachment::query()->where('label', 'Orientação nova')->sole());
    $this->context->run($this->a['tenant'], function () use ($attachment, $bytes): void {
        $cipher = Storage::disk('local')->get(app(TenantStorage::class)->path($attachment->path));
        expect($cipher)->not->toBe($bytes);
        expect(Crypt::decryptString($cipher))->toBe($bytes);
        expect($attachment->toArray())->not->toHaveKeys(['disk', 'path']);
    });
    $response = $this->get($this->base.'/'.$attachment->id.'/download')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'attachment; filename="orientacao-'.$attachment->id.'.png"');
    expect($response->getContent())->toBe($bytes);
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $events = DB::table('activity_log')->where('subject_type', ExamAttachment::class)->where('subject_id', $attachment->id)->get();
    expect($events->pluck('event')->all())->toBe(['exam_attachment_uploaded', 'exam_attachment_downloaded']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->deleteJson($this->base.'/'.$attachment->id.'/download')->assertStatus(405);
});

it('returns a synthetic blank PDF as an attachment without changing its bytes', function (): void {
    $bytes = "%PDF-1.4\n";
    $offsets = [];
    foreach (['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 10 10] >>'] as $index => $object) {
        $offsets[] = strlen($bytes);
        $bytes .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($bytes);
    $bytes .= "xref\n0 4\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $bytes .= sprintf("%010d 00000 n \n", $offset);
    }
    $bytes .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    $this->post($this->base, ['label' => 'PDF sintético vazio', 'file' => UploadedFile::fake()->createWithContent('orientacao.pdf', $bytes)])->assertRedirect();
    $attachment = $this->context->run($this->a['tenant'], fn () => ExamAttachment::query()->where('label', 'PDF sintético vazio')->sole());
    $response = $this->get($this->base.'/'.$attachment->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toBe($bytes);
});

it('refuses another exam in the same clinic and another tenant even with known attachment ids', function (): void {
    $wrong = 'http://'.$this->a['host'].'/exam-types/'.$this->otherA->public_id.'/attachments/'.$this->attachmentA->id.'/download';
    $this->get($wrong)->assertNotFound();
    $this->get($this->base.'/'.$this->attachmentB->id.'/download')->assertNotFound();
    $foreign = 'http://'.$this->a['host'].'/exam-types/'.$this->examB->public_id.'/attachments';
    $this->get($foreign)->assertNotFound();
    $this->post($foreign, ['label' => 'Inválido', 'file' => syntheticOrientation()])->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(DownloadExamAttachment::class)->execute($this->attachmentB))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(UploadExamAttachment::class)->execute($this->examB, 'Inválido', syntheticOrientationContents()))->toThrow(ModelNotFoundException::class);
    });
});

it('rejects ownership and storage injection and enforces immutable records and composite foreign keys', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'exam_type_id' => $this->examB->id, 'disk' => 'public', 'path' => '../.env', 'id' => 99999] as $field => $value) {
        $this->postJson($this->base, ['label' => 'Inválido', 'file' => syntheticOrientation(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(fn () => ExamAttachment::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->attachmentA->fresh()->forceFill(['label' => 'Alterado'])->save())->toThrow(LogicException::class);
        expect(fn () => $this->attachmentA->delete())->toThrow(LogicException::class);
        expect(fn () => DB::table('exam_type_attachments')->insert(['tenant_id' => $this->a['tenant']->id, 'exam_type_id' => $this->examB->id, 'label' => 'Inválido', 'path' => 'invalid']))->toThrow(QueryException::class);
    });
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('requires catalogue permissions and second factor and rechecks direct upload authorization', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Novo anexo');
    $this->get($this->base.'/'.$this->attachmentA->id.'/download')->assertOk();
    $this->postJson($this->base, ['label' => 'Inválido', 'file' => syntheticOrientation()])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(UploadExamAttachment::class)->execute($this->examA, 'Inválido', syntheticOrientationContents()))->toThrow(AuthorizationException::class);
    });
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.view'));
    $this->get($this->base.'/'.$this->attachmentA->id.'/download')->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
});

it('validates file content extension size and label and redisplays malformed old input safely', function (): void {
    $png = syntheticOrientation();
    $bytes = file_get_contents($png->getPathname());
    foreach ([UploadedFile::fake()->createWithContent('disfarce.png', '<?php echo 1;'), syntheticOrientation('disfarce.php'), UploadedFile::fake()->createWithContent('grande.png', $bytes.str_repeat('x', 2097153))] as $file) {
        $this->postJson($this->base, ['label' => 'Inválido', 'file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
    $this->postJson($this->base, ['label' => 'Sem arquivo'])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson($this->base, ['label' => str_repeat('x', 151), 'file' => syntheticOrientation()])->assertUnprocessable()->assertJsonValidationErrors('label');
    $this->from($this->base.'/create')->post($this->base, ['label' => ['invalid'], 'file' => syntheticOrientation()])->assertSessionHasErrors('label');
    $this->get($this->base.'/create')->assertOk();
    expect(DB::table('exam_type_attachments')->count())->toBe(2);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('compensates the file write and database insert when audit persistence fails', function (): void {
    $before = Storage::disk('local')->allFiles();
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base, ['label' => 'Não persistir', 'file' => syntheticOrientation()]))->toThrow(RuntimeException::class);
        expect(DB::table('exam_type_attachments')->count())->toBe(2);
        expect(Storage::disk('local')->allFiles())->toBe($before);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('does not commit a database row when private storage refuses the write', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->withoutExceptionHandling();
    expect(fn () => $this->post($this->base, ['label' => 'Não persistir', 'file' => syntheticOrientation()]))->toThrow(RuntimeException::class);
    expect(DB::table('exam_type_attachments')->count())->toBe(2);
});

it('fails closed for foreign storage paths invalid disks absent files and corrupted ciphertext', function (): void {
    $original = ['disk' => 'local', 'path' => $this->attachmentA->path];
    foreach ([['disk' => 'public'], ['path' => '../.env'], ['path' => $this->attachmentB->path]] as $change) {
        DB::table('exam_type_attachments')->where('tenant_id', $this->a['tenant']->id)->where('id', $this->attachmentA->id)->update($change + $original);
        $this->get($this->base.'/'.$this->attachmentA->id.'/download')->assertNotFound();
    }
    DB::table('exam_type_attachments')->where('tenant_id', $this->a['tenant']->id)->where('id', $this->attachmentA->id)->update($original);
    $this->context->run($this->a['tenant'], fn () => Storage::disk('local')->put(app(TenantStorage::class)->path($this->attachmentA->path), 'invalid-ciphertext'));
    $this->get($this->base.'/'.$this->attachmentA->id.'/download')->assertNotFound();
    $this->context->run($this->a['tenant'], fn () => Storage::disk('local')->delete(app(TenantStorage::class)->path($this->attachmentA->path)));
    $this->get($this->base.'/'.$this->attachmentA->id.'/download')->assertNotFound();
    expect(DB::table('activity_log')->where('event', 'exam_attachment_downloaded')->count())->toBe(0);
});

it('paginates only the selected exam and escapes labels without publishing storage paths', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        foreach (range(1, 20) as $number) {
            $attachment = $this->examA->attachments()->make(['label' => $number === 20 ? '<script>synthetic()</script>' : 'Paginado '.$number]);
            $attachment->forceFill(['disk' => 'local', 'path' => $this->attachmentA->path])->save();
        }
        $other = $this->otherA->attachments()->make(['label' => 'Não pertence ao exame']);
        $other->forceFill(['disk' => 'local', 'path' => 'unused'])->save();
    });
    $this->get($this->base)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertDontSee('<script>synthetic()</script>', false)->assertDontSee($this->attachmentA->path, false)->assertDontSee('Orientação B')->assertDontSee('Não pertence ao exame')->assertSee('page=2', false);
    $this->get($this->base.'?page=2')->assertOk()->assertSee('Orientação A')->assertDontSee('Orientação B');
});
