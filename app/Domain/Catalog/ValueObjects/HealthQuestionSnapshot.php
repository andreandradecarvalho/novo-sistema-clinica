<?php

declare(strict_types=1);

namespace App\Domain\Catalog\ValueObjects;

final readonly class HealthQuestionSnapshot
{
    public function __construct(
        public int $tenantId,
        public int $questionId,
        public string $code,
        public int $version,
        public string $label,
        public string $type,
        public ?array $options,
    ) {}

    public function answerColumns(): array
    {
        return [
            'question_id' => $this->questionId,
            'question_label_snapshot' => $this->label,
            'question_type_snapshot' => $this->type,
            'question_options_snapshot' => $this->options,
        ];
    }
}
