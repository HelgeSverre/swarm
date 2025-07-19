<?php

namespace HelgeSverre\Swarm\Tools;

use Exception;
use HelgeSverre\Swarm\Contracts\Tool;
use HelgeSverre\Swarm\Core\ToolResponse;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

class Playwright extends Tool
{
    protected static ?Process $browserProcess = null;

    protected static ?int $browserPort = null;

    protected static array $activeSessions = [];

    public function __construct(
        protected readonly ?string $nodePath = null,
        protected readonly ?string $playwrightPath = null
    ) {}

    public function __destruct()
    {
        // Clean up any remaining browser sessions
        foreach (array_keys(self::$activeSessions) as $sessionId) {
            $this->closeBrowser($sessionId);
        }
    }

    public function name(): string
    {
        return 'playwright';
    }

    public function description(): string
    {
        return 'Control a browser using Playwright for web automation, testing, and scraping';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'description' => 'The action to perform',
                'enum' => ['launch', 'navigate', 'screenshot', 'click', 'type', 'evaluate', 'wait_for', 'get_content', 'close'],
            ],
            'browser' => [
                'type' => 'string',
                'description' => 'Browser type (chromium, firefox, webkit). Default: chromium',
                'enum' => ['chromium', 'firefox', 'webkit'],
            ],
            'url' => [
                'type' => 'string',
                'description' => 'URL to navigate to (for navigate action)',
            ],
            'selector' => [
                'type' => 'string',
                'description' => 'CSS selector for element interaction (click, type, wait_for)',
            ],
            'text' => [
                'type' => 'string',
                'description' => 'Text to type (for type action)',
            ],
            'script' => [
                'type' => 'string',
                'description' => 'JavaScript code to evaluate (for evaluate action)',
            ],
            'path' => [
                'type' => 'string',
                'description' => 'File path for screenshot (for screenshot action)',
            ],
            'headless' => [
                'type' => 'boolean',
                'description' => 'Run browser in headless mode. Default: true',
            ],
            'timeout' => [
                'type' => 'number',
                'description' => 'Timeout in milliseconds. Default: 30000',
            ],
            'session_id' => [
                'type' => 'string',
                'description' => 'Session ID to reuse existing browser context',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $params): ToolResponse
    {
        $action = $params['action'];
        $sessionId = $params['session_id'] ?? 'default';

        try {
            return match ($action) {
                'launch' => $this->launchBrowser($params, $sessionId),
                'navigate' => $this->navigate($params, $sessionId),
                'screenshot' => $this->screenshot($params, $sessionId),
                'click' => $this->click($params, $sessionId),
                'type' => $this->type($params, $sessionId),
                'evaluate' => $this->evaluate($params, $sessionId),
                'wait_for' => $this->waitFor($params, $sessionId),
                'get_content' => $this->getContent($params, $sessionId),
                'close' => $this->closeBrowser($sessionId),
                default => throw new InvalidArgumentException("Unknown action: {$action}"),
            };
        } catch (Exception $e) {
            return ToolResponse::error("Playwright error: {$e->getMessage()}");
        }
    }

    protected function launchBrowser(array $params, string $sessionId): ToolResponse
    {
        if (isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::success([
                'message' => 'Browser already launched for this session',
                'session_id' => $sessionId,
            ]);
        }

        $browser = $params['browser'] ?? 'chromium';
        $headless = $params['headless'] ?? true;

        // Create a Node.js script to launch Playwright
        $script = $this->generateNodeScript('launch', [
            'browser' => $browser,
            'headless' => $headless,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            self::$activeSessions[$sessionId] = [
                'browser' => $browser,
                'launched_at' => time(),
            ];

            return ToolResponse::success([
                'message' => 'Browser launched successfully',
                'session_id' => $sessionId,
                'browser' => $browser,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to launch browser');
    }

    protected function navigate(array $params, string $sessionId): ToolResponse
    {
        $url = $params['url'] ?? throw new InvalidArgumentException('URL is required for navigate action');

        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $script = $this->generateNodeScript('navigate', [
            'url' => $url,
            'timeout' => $params['timeout'] ?? 30000,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => "Navigated to {$url}",
                'url' => $url,
                'title' => $result['data']['title'] ?? null,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to navigate');
    }

    protected function screenshot(array $params, string $sessionId): ToolResponse
    {
        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $path = $params['path'] ?? sys_get_temp_dir() . '/playwright_screenshot_' . time() . '.png';

        $script = $this->generateNodeScript('screenshot', [
            'path' => $path,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => 'Screenshot saved',
                'path' => $path,
                'size' => filesize($path),
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to take screenshot');
    }

    protected function click(array $params, string $sessionId): ToolResponse
    {
        $selector = $params['selector'] ?? throw new InvalidArgumentException('Selector is required for click action');

        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $script = $this->generateNodeScript('click', [
            'selector' => $selector,
            'timeout' => $params['timeout'] ?? 30000,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => "Clicked on element: {$selector}",
                'selector' => $selector,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to click element');
    }

    protected function type(array $params, string $sessionId): ToolResponse
    {
        $selector = $params['selector'] ?? throw new InvalidArgumentException('Selector is required for type action');
        $text = $params['text'] ?? throw new InvalidArgumentException('Text is required for type action');

        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $script = $this->generateNodeScript('type', [
            'selector' => $selector,
            'text' => $text,
            'timeout' => $params['timeout'] ?? 30000,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => "Typed text into element: {$selector}",
                'selector' => $selector,
                'text_length' => mb_strlen($text),
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to type text');
    }

    protected function evaluate(array $params, string $sessionId): ToolResponse
    {
        $script = $params['script'] ?? throw new InvalidArgumentException('Script is required for evaluate action');

        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $nodeScript = $this->generateNodeScript('evaluate', [
            'script' => $script,
        ]);

        $result = $this->executeNodeScript($nodeScript);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => 'Script executed successfully',
                'result' => $result['data']['result'] ?? null,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to evaluate script');
    }

    protected function waitFor(array $params, string $sessionId): ToolResponse
    {
        $selector = $params['selector'] ?? throw new InvalidArgumentException('Selector is required for wait_for action');

        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $script = $this->generateNodeScript('wait_for', [
            'selector' => $selector,
            'timeout' => $params['timeout'] ?? 30000,
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => "Element found: {$selector}",
                'selector' => $selector,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to wait for element');
    }

    protected function getContent(array $params, string $sessionId): ToolResponse
    {
        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found. Launch a browser first.');
        }

        $script = $this->generateNodeScript('get_content', [
            'selector' => $params['selector'] ?? 'body',
        ]);

        $result = $this->executeNodeScript($script);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => 'Content retrieved',
                'content' => $result['data']['content'] ?? '',
                'length' => mb_strlen($result['data']['content'] ?? ''),
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to get content');
    }

    protected function closeBrowser(string $sessionId): ToolResponse
    {
        if (! isset(self::$activeSessions[$sessionId])) {
            return ToolResponse::error('No browser session found.');
        }

        $script = $this->generateNodeScript('close', []);
        $result = $this->executeNodeScript($script);

        unset(self::$activeSessions[$sessionId]);

        if ($result['success']) {
            return ToolResponse::success([
                'message' => 'Browser closed successfully',
                'session_id' => $sessionId,
            ]);
        }

        return ToolResponse::error($result['error'] ?? 'Failed to close browser');
    }

    protected function generateNodeScript(string $action, array $params): string
    {
        // Generate a Node.js script that uses the Playwright API
        // This is a simplified example - in production, you'd have a more robust script
        $paramsJson = json_encode($params);

        return <<<JS
const { chromium, firefox, webkit } = require('playwright');

(async () => {
    let browser;
    let page;
    
    try {
        const params = {$paramsJson};
        const action = '{$action}';
        
        // Handle different actions
        switch (action) {
            case 'launch':
                const browserType = params.browser || 'chromium';
                const launchOptions = { headless: params.headless !== false };
                
                if (browserType === 'chromium') browser = await chromium.launch(launchOptions);
                else if (browserType === 'firefox') browser = await firefox.launch(launchOptions);
                else if (browserType === 'webkit') browser = await webkit.launch(launchOptions);
                
                page = await browser.newPage();
                console.log(JSON.stringify({ success: true }));
                break;
                
            case 'navigate':
                page = await browser.newPage();
                await page.goto(params.url, { timeout: params.timeout || 30000 });
                const title = await page.title();
                console.log(JSON.stringify({ success: true, data: { title } }));
                break;
                
            case 'screenshot':
                await page.screenshot({ path: params.path });
                console.log(JSON.stringify({ success: true }));
                break;
                
            case 'click':
                await page.click(params.selector, { timeout: params.timeout || 30000 });
                console.log(JSON.stringify({ success: true }));
                break;
                
            case 'type':
                await page.type(params.selector, params.text, { timeout: params.timeout || 30000 });
                console.log(JSON.stringify({ success: true }));
                break;
                
            case 'evaluate':
                const result = await page.evaluate(params.script);
                console.log(JSON.stringify({ success: true, data: { result } }));
                break;
                
            case 'wait_for':
                await page.waitForSelector(params.selector, { timeout: params.timeout || 30000 });
                console.log(JSON.stringify({ success: true }));
                break;
                
            case 'get_content':
                const content = await page.textContent(params.selector);
                console.log(JSON.stringify({ success: true, data: { content } }));
                break;
                
            case 'close':
                await browser.close();
                console.log(JSON.stringify({ success: true }));
                break;
        }
    } catch (error) {
        console.log(JSON.stringify({ success: false, error: error.message }));
    }
})();
JS;
    }

    protected function executeNodeScript(string $script): array
    {
        $nodePath = $this->nodePath ?? 'node';

        // Save script to temporary file
        $scriptPath = sys_get_temp_dir() . '/playwright_script_' . uniqid() . '.js';
        file_put_contents($scriptPath, $script);

        try {
            $process = new Process([$nodePath, $scriptPath]);
            $process->setTimeout(60); // 60 seconds timeout
            $process->run();

            $output = $process->getOutput();
            $error = $process->getErrorOutput();

            if (! $process->isSuccessful()) {
                return ['success' => false, 'error' => $error ?: 'Process failed'];
            }

            $result = json_decode(trim($output), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid JSON response: ' . $output];
            }

            return $result;
        } finally {
            // Clean up temporary script file
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }
}
