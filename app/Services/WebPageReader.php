<?php

namespace App\Services;

use Closure;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Fetches a web page and extracts its title and readable main text.
 */
class WebPageReader
{
    private const MAX_TEXT_LENGTH = 8000;

    private const MIN_REGION_LENGTH = 500;

    private const MAX_REDIRECTS = 3;

    private const MAX_RESPONSE_BYTES = 5_000_000;

    /**
     * Elements that never contain the main content.
     */
    private const NOISE_SELECTORS = 'script, style, noscript, svg, nav, header, footer, aside, form, iframe, template';

    /**
     * @param  (Closure(string): list<string>)|null  $resolveHost  Resolves a host name to IP addresses; DNS by default.
     */
    public function __construct(private ?Closure $resolveHost = null) {}

    /**
     * @return array{title: ?string, text: string}|null Null when the page could not be read.
     */
    public function read(string $url): ?array
    {
        $response = $this->fetch($url);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $contentType = Str::lower((string) $response->header('Content-Type'));

        if (Str::contains($contentType, 'text/plain')) {
            return ['title' => null, 'text' => $this->clean($response->body())];
        }

        if (! Str::contains($contentType, 'html')) {
            return null;
        }

        return $this->extract($response->body());
    }

    /**
     * Fetch the URL, following redirects by hand so every hop is checked against private networks.
     * The connection is pinned to the checked IP address, so DNS cannot change between check and request.
     */
    private function fetch(string $url): ?Response
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->publicTarget($url);

            if ($target === null) {
                return null;
            }

            $response = Http::timeout(15)
                ->withoutRedirecting()
                ->withUserAgent('Mozilla/5.0 (compatible; PKM-reader/1.0)')
                ->accept('text/html,text/plain;q=0.9,*/*;q=0.5')
                ->withOptions([
                    'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
                    'on_headers' => function (ResponseInterface $response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
                            throw new RuntimeException('Page is too large to read.');
                        }
                    },
                ])
                ->get($url);

            if (! $response->redirect()) {
                return $response;
            }

            $location = $response->header('Location');

            if ($location === '') {
                return null;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        return null;
    }

    /**
     * The host, port and IP address to connect to, or null when the URL is not http(s)
     * or points to a private, loopback, link-local or reserved address.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    private function publicTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = Str::lower($parts['scheme'] ?? '');
        $host = trim($parts['host'] ?? '', '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        $isPublic = fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($ips === [] || ! collect($ips)->every($isPublic)) {
            return null;
        }

        return [
            'host' => $parts['host'],
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ip' => str_contains($ips[0], ':') ? "[{$ips[0]}]" : $ips[0],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolveHost !== null) {
            return ($this->resolveHost)($host);
        }

        $ipv6 = collect(@dns_get_record($host, DNS_AAAA) ?: [])->pluck('ipv6')->all();

        return [...(@gethostbynamel($host) ?: []), ...$ipv6];
    }

    /**
     * @return array{title: ?string, text: string}|null
     */
    public function extract(string $html): ?array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $title = $document->querySelector('meta[property="og:title"]')?->getAttribute('content')
            ?? $document->querySelector('title')?->textContent;
        $description = $document->querySelector('meta[name="description"]')?->getAttribute('content')
            ?? $document->querySelector('meta[property="og:description"]')?->getAttribute('content');

        foreach ($document->querySelectorAll(self::NOISE_SELECTORS) as $node) {
            $node->remove();
        }

        $text = $this->clean(trim(($description ?? '')."\n\n".$this->mainText($document)));

        if ($text === '') {
            return null;
        }

        return [
            'title' => filled($title) ? Str::limit(Str::squish($title), 250, '') : null,
            'text' => $text,
        ];
    }

    /**
     * The longest article/main region; pages often contain small teaser <article> cards,
     * so fall back to the whole body when no region holds substantial text.
     */
    private function mainText(HTMLDocument $document): string
    {
        $longest = '';

        foreach ($document->querySelectorAll('article, main, [role="main"]') as $region) {
            $text = trim($region->textContent ?? '');

            if (mb_strlen($text) > mb_strlen($longest)) {
                $longest = $text;
            }
        }

        if (mb_strlen($longest) >= self::MIN_REGION_LENGTH) {
            return $longest;
        }

        return trim($document->body?->textContent ?? '');
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*(\n\s*)+/', "\n\n", $text) ?? $text;

        return Str::limit(trim($text), self::MAX_TEXT_LENGTH, '…');
    }
}
