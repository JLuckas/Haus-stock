<?php

namespace App\Enums;

enum StockProcessingOutcome: string
{
    case Processed = 'processed';
    case AlreadyProcessed = 'already_processed';
    case SkippedCancelled = 'skipped_cancelled';
}
