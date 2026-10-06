<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Source\BrowserRequest;
use RuntimeException;

final readonly class BrowserWebClient implements WebClient
{
    private const int MAX_HTML_BYTES = 5_000_000;

    public function __construct(private string $scriptPath)
    {
    }

    /**
     * @throws \JsonException
     */
    #[\Override]
    public function render(BrowserRequest $request): string
    {
        $process = proc_open(['node', $this->scriptPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start browser client.');
        }

        fwrite($pipes[0], json_encode([
            'url' => $request->url,
            'actions' => $request->actions,
            'ready' => ['selector' => $request->readySelector],
            'timeoutMs' => $request->timeoutMs,
            'allowLocalFile' => $request->allowLocalFile,
            'allowedHosts' => $request->allowedHosts,
        ], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $html = stream_get_contents($pipes[1], self::MAX_HTML_BYTES + 1);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);

        if ($status !== 0 || $html === false || $html === '' || strlen($html) > self::MAX_HTML_BYTES) {
            throw new RuntimeException('Browser render failed: ' . trim($error ?: 'empty HTML'));
        }
        return $html;
    }
}
