<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Jobs;

use Gabrielesbaiz\GoogleTranslateToolkit\Contracts\Translatable;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateAttributesJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<int, string>|null  $attributes
     */
    public function __construct(
        public readonly Model $model,
        public readonly ?string $target = null,
        public readonly ?string $source = null,
        public readonly ?array $attributes = null,
        public readonly bool $overwrite = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = $this->model;

        if (! $model instanceof Translatable) {
            return;
        }

        $model->translateAttributes(
            to: $this->target,
            from: $this->source,
            attributes: $this->attributes,
            overwrite: $this->overwrite,
        )->save();
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return implode(':', [$this->model::class, (string) $this->model->getKey(), (string) $this->target]);
    }
}
