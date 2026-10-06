<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Source\BrowserRequest;

interface WebClient
{
    public function render(BrowserRequest $request): string;
}
