<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Reads and writes the lang/{locale}/*.php and lang/{locale}.json files.
 */
final class LangFiles
{
    /**
     * Create a new lang files instance.
     */
    public function __construct(private readonly string $basePath) {}

    /**
     * Create a new lang files instance for the application lang path.
     */
    public static function make(?string $basePath = null): self
    {
        return new self($basePath ?? (function_exists('lang_path') ? lang_path() : base_path('lang')));
    }

    /**
     * Get the path to the given lang file parts.
     */
    public function path(string ...$parts): string
    {
        return rtrim($this->basePath, '/').'/'.implode('/', $parts);
    }

    /**
     * Determine if any lang file exists for the given locale.
     */
    public function exists(string $locale): bool
    {
        return File::isDirectory($this->path($locale)) || File::exists($this->path($locale.'.json'));
    }

    /**
     * Get every group of the given locale mapped to its flattened lines.
     *
     * @return Collection<string, array<string, string>>
     */
    public function read(string $locale): Collection
    {
        $groups = collect();

        foreach (File::glob($this->path($locale, '*.php')) ?: [] as $file) {
            $lines = include $file;

            if (is_array($lines)) {
                $groups[pathinfo($file, PATHINFO_FILENAME)] = $this->flatten($lines);
            }
        }

        $json = $this->path($locale.'.json');

        if (File::exists($json)) {
            $decoded = json_decode(File::get($json), true);

            if (is_array($decoded)) {
                $groups['__json'] = array_map(strval(...), $decoded);
            }
        }

        return $groups;
    }

    /**
     * Write the given lines to the lang file for the locale and group.
     *
     * @param  array<string, string>  $lines
     */
    public function write(string $locale, string $group, array $lines): string
    {
        if ($group === '__json') {
            $path = $this->path($locale.'.json');

            File::ensureDirectoryExists(dirname($path));
            File::put($path, (string) json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

            return $path;
        }

        $path = $this->path($locale, $group.'.php');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$this->export(Arr::undot($lines)).";\n");

        return $path;
    }

    /**
     * Flatten the given lines into dot notation, keeping only the strings.
     *
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private function flatten(array $lines): array
    {
        return collect(Arr::dot($lines))
            ->filter(fn (mixed $value) => is_string($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Export the given array as the PHP source of a lang file.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function export(array $value, int $depth = 1): string
    {
        $indent = str_repeat('    ', $depth);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $lines[] = sprintf(
                '%s%s => %s,',
                $indent,
                is_int($key) ? $key : "'".str_replace("'", "\\'", (string) $key)."'",
                is_array($item)
                    ? $this->export($item, $depth + 1)
                    : "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $item)."'",
            );
        }

        $lines[] = str_repeat('    ', $depth - 1).']';

        return implode("\n", $lines);
    }
}
