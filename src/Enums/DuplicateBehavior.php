<?php

namespace RaiseStudio\Import\Enums;

enum DuplicateBehavior: string
{
    case Skip = 'skip';
    case Update = 'update';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Skip => __('raise-import::messages.duplicate_behavior.skip'),
            self::Update => __('raise-import::messages.duplicate_behavior.update'),
            self::Error => __('raise-import::messages.duplicate_behavior.error'),
        };
    }
}
