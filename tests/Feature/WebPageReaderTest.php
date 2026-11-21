<?php

use App\Services\WebPageReader;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('it extracts the title and main text without page chrome', function () {
    Http::fake(['example.com/*' => Http::response(<<<'HTML'
        <html>
            <head>
                <title>Fallback title</title>
                <meta property="og:title" content="Insulating the attic in 5 steps">
                <meta name="description" content="A practical guide.">
                <script>tracking()</script>
            </head>
            <body>
                <nav>Home | Blog</nav>
                <article><h1>Insulation</h1><p>Use at least 12 cm of glass wool.</p></article>
                <footer>© 2026</footer>
            </body>
        </html>
        HTML, 200, ['Content-Type' => 'text/html'])]);

    $page = app(WebPageReader::class)->read('https://example.com/attic');

    expect($page['title'])->toBe('Insulating the attic in 5 steps')
        ->and($page['text'])->toContain('A practical guide.')
        ->and($page['text'])->toContain('12 cm of glass wool')
        ->and($page['text'])->not->toContain('tracking')
        ->and($page['text'])->not->toContain('Home | Blog')
        ->and($page['text'])->not->toContain('© 2026');
});

test('it returns null for failed requests and non-text content', function (int $status, string $contentType) {
    Http::fake(['example.com/*' => Http::response('x', $status, ['Content-Type' => $contentType])]);

    expect(app(WebPageReader::class)->read('https://example.com/file'))->toBeNull();
})->with([
    'not found' => [404, 'text/html'],
    'pdf' => [200, 'application/pdf'],
]);

test('it only reads http urls', function () {
    expect(app(WebPageReader::class)->read('file:///etc/passwd'))->toBeNull();

    Http::assertNothingSent();
});

test('it limits very long pages', function () {
    Http::fake(['example.com/*' => Http::response('<p>'.str_repeat('woord ', 5000).'</p>', 200, ['Content-Type' => 'text/html'])]);

    expect(mb_strlen(app(WebPageReader::class)->read('https://example.com/lang')['text']))->toBeLessThanOrEqual(8001);
});

test('it prefers the longest content region over teaser cards', function () {
    $story = str_repeat('Roof insulation saves a lot of energy. ', 30);
    Http::fake(['example.com/*' => Http::response(
        "<html><body><article>Read also: window frames</article><main><h1>Roof insulation</h1><p>{$story}</p></main></body></html>",
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $page = app(WebPageReader::class)->read('https://example.com/roof');

    expect($page['text'])->toContain('Roof insulation saves a lot of energy.')
        ->and($page['text'])->not->toContain('Read also: window frames');
});

test('it refuses urls that point to private or local addresses', function (string $url) {
    expect(app(WebPageReader::class)->read($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1:8000/admin',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'private network' => 'http://192.168.1.1/',
    'ipv6 loopback' => 'http://[::1]/',
    'host resolving to a private address' => 'https://intern.example/secret',
]);

test('it follows redirects only to public addresses', function () {
    Http::fake([
        'example.com/old' => Http::response('', 301, ['Location' => '/new']),
        'example.com/new' => Http::response('<p>Moved article</p>', 200, ['Content-Type' => 'text/html']),
        'example.com/leak' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
    ]);

    expect(app(WebPageReader::class)->read('https://example.com/old')['text'])->toBe('Moved article')
        ->and(app(WebPageReader::class)->read('https://example.com/leak'))->toBeNull();

    Http::assertSentCount(3);
});
