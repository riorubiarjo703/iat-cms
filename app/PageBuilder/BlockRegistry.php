<?php

namespace App\PageBuilder;

/**
 * The set of block types this installation knows about.
 *
 * Registered explicitly at boot rather than auto-discovered, so a stray class
 * in the directory cannot silently become an editor option.
 */
class BlockRegistry
{
    /** @var array<string, class-string<BlockContract>> */
    private array $blocks = [];

    /** @param class-string<BlockContract> $class */
    public function register(string $class): void
    {
        $this->blocks[$class::type()] = $class;
    }

    /** @return array<string, class-string<BlockContract>> */
    public function all(): array
    {
        return $this->blocks;
    }

    /** @return class-string<BlockContract>|null */
    public function get(string $type): ?string
    {
        return $this->blocks[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    /**
     * Grouped for the block picker.
     *
     * @return array<string, array<int, class-string<BlockContract>>>
     */
    public function byCategory(): array
    {
        $grouped = [];

        foreach ($this->blocks as $class) {
            $grouped[$class::category()][] = $class;
        }

        return $grouped;
    }

    /**
     * The view for a type, or an empty string when the type is unknown. The
     * dispatcher turns that into a placeholder — a page may carry blocks from
     * a version that no longer exists, and that must not be fatal.
     */
    public function resolveRenderView(string $type): string
    {
        $class = $this->get($type);

        return $class ? $class::renderView() : '';
    }
}
