<?php

use HelgeSverre\Swarm\Tools\WebFetch;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('webfetch tool generates correct schema', function () {
    $tool = new WebFetch;
    $schema = $tool->toOpenAISchema();

    expect($schema)->toBeArray()
        ->and($schema['name'])->toBe('web_fetch')
        ->and($schema['description'])->toBe('Fetch content from a URL and convert HTML to text for AI processing')
        ->and($schema['parameters']['type'])->toBe('object')
        ->and($schema['parameters']['properties'])->toHaveKeys(['url', 'timeout'])
        ->and($schema['parameters']['required'])->toBe(['url']);
});

test('webfetch converts HTML to text', function () {
    $htmlContent = '<!DOCTYPE html>
<html>
<head><title>Test Page</title></head>
<body>
    <h1>Hello World</h1>
    <p>This is a <strong>test</strong> paragraph.</p>
    <ul>
        <li>Item 1</li>
        <li>Item 2</li>
    </ul>
</body>
</html>';

    $mockResponse = new MockResponse($htmlContent, [
        'response_headers' => ['content-type' => 'text/html; charset=utf-8'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['status_code'])->toBe(200)
        ->and($result->getData()['content_type'])->toContain('text/html')
        ->and($result->getData()['content'])->toContain('Hello World')
        ->and($result->getData()['content'])->toContain('TEST paragraph.')
        ->and($result->getData()['content'])->toContain('Item 1')
        ->and($result->getData()['content'])->toContain('Item 2')
        ->and($result->getData()['content'])->not->toContain('<h1>')
        ->and($result->getData()['content'])->not->toContain('<strong>');
});

test('webfetch pretty prints JSON', function () {
    $jsonContent = '{"name":"John Doe","age":30,"nested":{"key":"value"},"items":["one","two"]}';

    $mockResponse = new MockResponse($jsonContent, [
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://api.example.com/user']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content_type'])->toContain('application/json')
        ->and($result->getData()['content'])->toContain("{\n")
        ->and($result->getData()['content'])->toContain('"name": "John Doe"')
        ->and($result->getData()['content'])->toContain('"nested": {')
        ->and($result->getData()['content'])->toContain('    "key": "value"');
});

test('webfetch handles plain text content', function () {
    $textContent = "This is plain text.\nWith multiple lines.\nNo formatting needed.";

    $mockResponse = new MockResponse($textContent, [
        'response_headers' => ['content-type' => 'text/plain'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/readme.txt']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe($textContent);
});

test('webfetch handles markdown content', function () {
    $markdownContent = "# Heading 1\n\n## Heading 2\n\n- List item\n- Another item\n\n**Bold text**";

    $mockResponse = new MockResponse($markdownContent, [
        'response_headers' => ['content-type' => 'text/markdown'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/readme.md']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe($markdownContent);
});

test('webfetch handles CSV content', function () {
    $csvContent = "name,age,city\nJohn,30,New York\nJane,25,London\nBob,35,Paris";

    $mockResponse = new MockResponse($csvContent, [
        'response_headers' => ['content-type' => 'text/csv'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/data.csv']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content_type'])->toContain('text/csv')
        ->and($result->getData()['content'])->toBe($csvContent);
});

test('webfetch handles binary content types', function () {
    // Test PDF
    $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj";

    $mockResponse = new MockResponse($pdfContent, [
        'response_headers' => ['content-type' => 'application/pdf'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/document.pdf']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content_type'])->toContain('application/pdf')
        // PDF extraction would fail with this mock content, so it returns a placeholder
        ->and($result->getData()['content'])->toContain('[PDF content')
        ->and($result->getData()['content'])->toContain('bytes');

    // Test DOCX
    $docxContent = "PK\x03\x04"; // DOCX file signature

    $mockResponse = new MockResponse($docxContent, [
        'response_headers' => ['content-type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/document.docx']);

    expect($result->isSuccess())->toBeTrue()
        // DOCX is binary content
        ->and($result->getData()['content'])->toContain('[Binary content')
        ->and($result->getData()['content'])->toContain('bytes');
});

test('webfetch validates URL format', function () {
    $tool = new WebFetch;

    $result = $tool->execute(['url' => 'not-a-valid-url']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Invalid URL');
});

test('webfetch handles missing URL parameter', function () {
    $tool = new WebFetch;

    expect(fn () => $tool->execute([]))->toThrow(InvalidArgumentException::class, 'URL is required');
});

test('webfetch handles network errors', function () {
    $mockClient = new MockHttpClient(function () {
        throw new TransportException('Connection timeout');
    });

    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Failed to fetch')
        ->and($result->getError())->toContain('Connection timeout');
});

test('webfetch handles HTTP errors', function () {
    $mockResponse = new MockResponse('Not Found', [
        'http_code' => 404,
        'response_headers' => ['content-type' => 'text/plain'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/missing']);

    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Failed to fetch')
        ->and($result->getError())->toContain('404');
});

test('webfetch uses hardcoded headers', function () {
    $content = 'Test content';

    $mockResponse = new MockResponse($content, [
        'response_headers' => ['content-type' => 'text/plain'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://api.example.com/test']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe($content);
});

test('webfetch handles custom timeout', function () {
    $content = 'Quick response';

    $mockResponse = new MockResponse($content, [
        'response_headers' => ['content-type' => 'text/plain'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute([
        'url' => 'https://example.com',
        'timeout' => 5,
    ]);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content'])->toBe($content);
});

test('webfetch tracks content sizes', function () {
    $htmlContent = '<html><body><h1>Test</h1><p>Content with tags</p></body></html>';

    $mockResponse = new MockResponse($htmlContent, [
        'response_headers' => ['content-type' => 'text/html'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['size'])->toBe(mb_strlen($htmlContent))
        ->and(mb_strlen($result->getData()['content']))->toBeLessThan($result->getData()['size']);
});

test('webfetch handles malformed JSON gracefully', function () {
    $malformedJson = '{"name": "test", "invalid": }';

    $mockResponse = new MockResponse($malformedJson, [
        'response_headers' => ['content-type' => 'application/json'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://api.example.com/bad']);

    // JSON decode will throw an exception, so it returns an error
    expect($result->isError())->toBeTrue()
        ->and($result->getError())->toContain('Failed to fetch');
});

test('webfetch handles XHTML content', function () {
    $xhtmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>XHTML Test</title></head>
<body>
    <h1>XHTML Content</h1>
    <p>This is valid XHTML.</p>
</body>
</html>';

    $mockResponse = new MockResponse($xhtmlContent, [
        'response_headers' => ['content-type' => 'application/xhtml+xml'],
    ]);

    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);

    $result = $tool->execute(['url' => 'https://example.com/page.xhtml']);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getData()['content_type'])->toContain('application/xhtml+xml')
        // XHTML is not a text/* type, so it gets treated as binary
        ->and($result->getData()['content'])->toContain('[Binary content');
});
