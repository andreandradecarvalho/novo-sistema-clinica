<?php

declare(strict_types=1);

namespace App\Domain\Catalog\ValueObjects;

use finfo;
use Illuminate\Validation\ValidationException;

final readonly class AttachmentContents
{
    public const int MAX_BYTES = 2097152;

    public const array MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public string $extension;

    public function __construct(public string $bytes)
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'O arquivo deve ter conteúdo e no máximo 2 MB.']);
        }
        $mime = new finfo(FILEINFO_MIME_TYPE)->buffer($bytes);
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw ValidationException::withMessages(['file' => 'Envie um arquivo PDF, PNG ou JPEG.']);
        }
        $this->extension = self::MIME_EXTENSIONS[$mime];
    }
}
