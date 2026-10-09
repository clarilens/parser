<?php

declare(strict_types=1);

namespace Clarilens\Parser\Result;

enum CrawlFailureCode: string
{
    case SourceError = 'source_error';
    case RetryExhausted = 'retry_exhausted';
    case IdentityConflict = 'identity_conflict';
    case NoProgress = 'no_progress';
    case CountMismatch = 'count_mismatch';
    case PriorCountThreshold = 'prior_count_threshold';
    case InvalidExpectedCount = 'invalid_expected_count';
    case PageLimitExhausted = 'page_limit_exhausted';
    case ClickLimitExhausted = 'click_limit_exhausted';
    case ItemLimitExhausted = 'item_limit_exhausted';
    case DurationLimitExhausted = 'duration_limit_exhausted';
}
