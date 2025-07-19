# WebFetch Tool Documentation

## Overview

The WebFetch tool allows the AI agent to fetch content from URLs and convert it to a format suitable for AI processing. It automatically handles HTML-to-text conversion, JSON pretty-printing, and various content types.

## Features

- **HTML to Text Conversion**: Automatically converts HTML pages to clean, readable text using the `crwlr/html-2-text` library
- **JSON Pretty Printing**: Formats JSON responses for better readability
- **Multiple Content Types**: Handles HTML, JSON, CSV, plain text, markdown, and binary files
- **Custom Headers**: Supports custom HTTP headers for authentication
- **Timeout Control**: Configurable request timeout
- **Error Handling**: Graceful handling of network errors, HTTP errors, and invalid URLs
- **Size Tracking**: Reports both raw and processed content sizes

## Usage

### Basic Usage

```php
$tool = new WebFetch();
$result = $tool->execute(['url' => 'https://example.com']);

if ($result->isSuccess()) {
    $data = $result->getData();
    echo $data['content']; // Processed content
    echo $data['status_code']; // HTTP status code
    echo $data['content_type']; // Content type
}
```

### With Custom Headers

```php
$result = $tool->execute([
    'url' => 'https://api.example.com/protected',
    'headers' => [
        'Authorization' => 'Bearer token123',
        'X-API-Key' => 'secret',
    ],
]);
```

### With Custom Timeout

```php
$result = $tool->execute([
    'url' => 'https://slow-api.example.com',
    'timeout' => 60, // 60 seconds
]);
```

## Testing

The tool includes comprehensive test coverage with mocked HTTP clients:

```bash
# Run all WebFetch tests
./vendor/bin/pest tests/Unit/Tools/WebFetchTest.php

# Run with coverage
composer test:coverage
```

## Implementation Details

### Dependency Injection for Testing

The tool accepts an optional `HttpClientInterface` in the constructor for testing:

```php
$mockClient = new MockHttpClient([$mockResponse]);
$tool = new WebFetch($mockClient);
```

### Content Processing

1. **HTML**: Converted to markdown-like text format
2. **JSON**: Pretty-printed with proper indentation
3. **Plain text/CSV/Markdown**: Passed through unchanged
4. **Binary files**: Returned as-is (PDF, DOCX, etc.)

### Error Handling

- **Invalid URLs**: Returns error before making request
- **Network errors**: Caught and reported with descriptive message
- **HTTP 4xx/5xx errors**: Detected and reported with status code
- **Malformed JSON**: Falls back to raw content

## OpenAI Function Schema

The tool generates the following schema for OpenAI function calling:

```json
{
  "name": "web_fetch",
  "description": "Fetch content from a URL and convert HTML to text for AI processing",
  "parameters": {
    "type": "object",
    "properties": {
      "url": {
        "type": "string",
        "description": "The URL to fetch content from"
      },
      "headers": {
        "type": "object",
        "description": "Optional HTTP headers to include in the request"
      },
      "timeout": {
        "type": "number",
        "description": "Request timeout in seconds (default: 30)"
      }
    },
    "required": ["url"]
  }
}
```

## Future Enhancements

1. **Caching**: Add response caching to avoid repeated requests
2. **Rate Limiting**: Implement rate limiting for API endpoints
3. **Proxy Support**: Add proxy configuration options
4. **Content Extraction**: More sophisticated content extraction (e.g., main article content)
5. **File Downloads**: Support for downloading and processing files
6. **Authentication**: Built-in support for common auth methods (OAuth, API keys)
7. **Retry Logic**: Automatic retry with exponential backoff
8. **Response Streaming**: Support for large file downloads with streaming