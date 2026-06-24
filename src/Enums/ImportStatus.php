<?php

namespace RaiseStudio\Import\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Previewing = 'previewing';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('raise-import::messages.import_status.pending'),
            self::Previewing => __('raise-import::messages.import_status.previewing'),
            self::Processing => __('raise-import::messages.import_status.processing'),
            self::Completed => __('raise-import::messages.import_status.completed'),
            self::Failed => __('raise-import::messages.import_status.failed'),
            self::Partial => __('raise-import::messages.import_status.partial'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Previewing => 'info',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Partial => 'warning',
        };
    }
}
