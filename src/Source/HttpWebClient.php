<?php

declare(strict_types=1);

namespace Clarilens\Parser\Source;

use Clarilens\Parser\Source\HttpFetchRequest;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpWebClient
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = new NoPrivateNetworkHttpClient($client ?? HttpClient::create());
    }

    public function fetch(HttpFetchRequest $request): string
    {
        $response = $this->client->request('GET', $request->url, [
            'max_redirects' => 0,
            'max_duration' => $request->timeoutMs / 1000,
            'timeout' => $request->timeoutMs / 1000,
            'buffer' => false,
            'on_progress' => static function (int $downloaded) use ($request): void {
                if ($downloaded > $request->maxBytes) {
                    throw new RuntimeException('HTTP source is too large.');
                }
            },
        ]);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $response->cancel();
            throw new RuntimeException('HTTP source returned an unsuccessful status or redirect.');
        }
        $content = $response->getContent(false);
        if (strlen($content) > $request->maxBytes) {
            throw new RuntimeException('HTTP source is too large.');
        }
        return $content;
    }
}
