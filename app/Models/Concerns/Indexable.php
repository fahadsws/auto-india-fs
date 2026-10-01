<?php

namespace App\Models\Concerns;

use App\Services\KnowledgeBase;

/**
 * Keeps the AI knowledge base (knowledge_chunks) in sync with a model.
 * The model must implement toKnowledge(): ?array{title,content,url,image}.
 */
trait Indexable
{
    abstract public function toKnowledge(): ?array;

    abstract public static function knowledgeType(): string;

    public static function bootIndexable(): void
    {
        static::saved(fn ($m) => KnowledgeBase::sync($m));
        static::deleted(fn ($m) => KnowledgeBase::forget($m));
    }
}
