<?php

/**
 * SwarmPHP: Treating AI Agents like HTTP Requests
 *
 * Core Philosophy: Agents are like web pages - they have URLs, accept parameters,
 * return responses, and can redirect to other agents. Simple, stateless by default,
 * but with session support when needed.
 */

// =============================================================================
// CORE CONCEPT: Agents as "Routes"
// =============================================================================

class AgentRouter
{
    private $routes = [];

    private $middleware = [];

    // Register agents like routes
    public function agent(string $pattern, callable $handler): self
    {
        $this->routes[$pattern] = $handler;

        return $this;
    }

    // Process a "request" to an agent
    public function dispatch(string $agentUrl, array $params = []): AgentResponse
    {
        $request = new AgentRequest($agentUrl, $params);

        // Apply middleware (rate limiting, auth, logging, etc.)
        foreach ($this->middleware as $middleware) {
            $request = $middleware($request);
        }

        // Find and execute the agent
        foreach ($this->routes as $pattern => $handler) {
            if ($this->matches($pattern, $agentUrl)) {
                return $handler($request);
            }
        }

        throw new AgentNotFoundException("No agent found for: {$agentUrl}");
    }
}

// =============================================================================
// FLUENT AGENT BUILDING
// =============================================================================

class Agent
{
    private $capabilities = [];

    private $memory = [];

    private $instructions = '';

    // The agent becomes a callable that returns responses
    public function __invoke(AgentRequest $request): AgentResponse
    {
        $context = $this->buildContext($request);
        $response = $this->process($context);

        return AgentResponse::create($response)
            ->withAgent($this)
            ->withRequest($request);
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function withCapability(string $capability, $config = null): self
    {
        $this->capabilities[$capability] = $config;

        return $this;
    }

    public function withMemory(string $type = 'session'): self
    {
        $this->memory['type'] = $type;

        return $this;
    }

    public function withInstructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }
}

// =============================================================================
// SWARM AS A SIMPLE PIPELINE
// =============================================================================

class Swarm
{
    private $agents = [];

    private $strategy = 'sequential';

    public static function create(): self
    {
        return new self;
    }

    public function add(Agent $agent, string $condition = 'always'): self
    {
        $this->agents[] = ['agent' => $agent, 'condition' => $condition];

        return $this;
    }

    public function parallel(): self
    {
        $this->strategy = 'parallel';

        return $this;
    }

    public function voting(): self
    {
        $this->strategy = 'voting';

        return $this;
    }

    public function execute(AgentRequest $request): SwarmResponse
    {
        return match ($this->strategy) {
            'sequential' => $this->executeSequential($request),
            'parallel' => $this->executeParallel($request),
            'voting' => $this->executeVoting($request),
        };
    }
}

// =============================================================================
// SIMPLE USAGE EXAMPLES
// =============================================================================

// Example 1: Single Agent (like defining a route)
$router = new AgentRouter;

$router->agent('/customer-service', function (AgentRequest $request) {
    return Agent::create('CustomerService')
        ->withCapability('web_search')
        ->withCapability('knowledge_base', ['source' => 'company_docs'])
        ->withMemory('session')
        ->withInstructions('Be helpful and empathetic')
        ->__invoke($request);
});

// Use it like making an HTTP request
$response = $router->dispatch('/customer-service', [
    'message' => 'I need help with my order',
    'user_id' => 12345,
]);

echo $response->getMessage();

// =============================================================================
// Example 2: Agent Swarm (like a microservice)
// =============================================================================

$codeReviewSwarm = Swarm::create()
    ->add(
        Agent::create('SyntaxChecker')
            ->withCapability('code_analysis')
            ->withInstructions('Check for syntax errors and style issues')
    )
    ->add(
        Agent::create('SecurityAuditor')
            ->withCapability('security_scan')
            ->withInstructions('Look for security vulnerabilities'),
        'when:security_level=high'
    )
    ->add(
        Agent::create('PerformanceAnalyzer')
            ->withCapability('performance_analysis')
            ->withInstructions('Suggest performance improvements')
    )
    ->voting(); // All agents vote on final recommendation

$router->agent('/code-review', function (AgentRequest $request) use ($codeReviewSwarm) {
    return $codeReviewSwarm->execute($request);
});

// =============================================================================
// Example 3: Agent Redirects (like HTTP redirects)
// =============================================================================

$router->agent('/help', function (AgentRequest $request) {
    $intent = detectIntent($request->getMessage());

    return match ($intent) {
        'technical' => AgentResponse::redirect('/tech-support', $request->getParams()),
        'billing' => AgentResponse::redirect('/billing-agent', $request->getParams()),
        'general' => AgentResponse::redirect('/customer-service', $request->getParams()),
        default => AgentResponse::create('I need more information to help you.')
    };
});

// =============================================================================
// Example 4: Middleware (like HTTP middleware)
// =============================================================================

$router->middleware(function (AgentRequest $request) {
    // Rate limiting
    if (RateLimit::exceeded($request->getUserId())) {
        throw new TooManyRequestsException;
    }

    // Logging
    Log::info("Agent request: {$request->getUrl()}", $request->getParams());

    return $request;
});

// =============================================================================
// SIMPLE STATE MANAGEMENT
// =============================================================================

class Conversation
{
    public static function start(string $sessionId): self
    {
        return new self($sessionId);
    }

    public function ask(string $agentUrl, string $message, array $context = []): AgentResponse
    {
        $params = array_merge($context, [
            'message' => $message,
            'session_id' => $this->sessionId,
            'conversation_history' => $this->getHistory(),
        ]);

        $response = app(AgentRouter::class)->dispatch($agentUrl, $params);

        $this->addToHistory($message, $response->getMessage());

        return $response;
    }

    public function handoff(string $fromAgent, string $toAgent, array $context = []): void
    {
        $this->addToHistory(
            '[SYSTEM]',
            "Conversation handed off from {$fromAgent} to {$toAgent}"
        );
    }
}

// Usage:
$chat = Conversation::start('user_12345');
$response = $chat->ask('/customer-service', 'I have a problem with my order');

if ($response->needsEscalation()) {
    $chat->handoff('customer-service', 'human-agent');
    $response = $chat->ask('/human-agent', 'Previous agent escalated this issue');
}

// =============================================================================
// TESTING MADE SIMPLE
// =============================================================================

// Test agents like testing HTTP endpoints
function testCustomerServiceAgent()
{
    $request = AgentRequest::fake([
        'message' => 'Test message',
        'user_id' => 999,
    ]);

    $response = app(AgentRouter::class)->dispatch('/customer-service', $request->getParams());

    assert($response->getStatus() === 'success');
    assert(str_contains($response->getMessage(), 'help'));
}

// =============================================================================
// DEPLOYMENT AS SIMPLE AS PHP
// =============================================================================

// agents.php - Your agent definitions
require_once 'vendor/autoload.php';

$router = new AgentRouter;

// Define all your agents here...
$router->agent('/support', $supportAgent);
$router->agent('/sales', $salesAgent);

// index.php - Handle requests
$agentUrl = $_GET['agent'] ?? '/default';
$params = $_POST ?: $_GET;

try {
    $response = $router->dispatch($agentUrl, $params);
    echo json_encode($response->toArray());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
