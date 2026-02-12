<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

/**
 * Intelligent conversation buffer with relevance-based context selection
 *
 * Uses modern PHP patterns:
 * - Constructor property promotion
 * - Readonly properties for immutability
 * - Strong typing throughout
 * - Named arguments for clarity
 *
 * Replaces simple chronological history with smart context management:
 * - Bounded memory usage (no unbounded growth)
 * - Relevance scoring for context selection
 * - Multi-factor ranking: relevance + recency + importance
 */
class ConversationBuffer
{
    /**
     * @var array<array{role: string, content: string, timestamp: int, importance: float, id: string}>
     */
    protected array $messages = [];

    /**
     * @var array<string, float>
     */
    protected array $relevanceCache = [];

    public function __construct(
        protected readonly int $maxSize = 100
    ) {}

    /**
     * Add message to buffer with automatic pruning
     */
    public function addMessage(string $role, string $content): void
    {
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
            'importance' => $this->calculateImportance(role: $role, content: $content),
            'id' => uniqid(prefix: 'msg_'),
        ];

        $this->messages[] = $message;

        // Prune if over limit
        if (count($this->messages) > $this->maxSize) {
            $this->pruneMessages();
        }

        // Clear relevance cache when new message added
        $this->relevanceCache = [];
    }

    /**
     * Get optimal context for current task using intelligent selection
     *
     * @return array<array{role: string, content: string}>
     */
    public function getOptimalContext(string $currentTask, int $tokenBudget = 4000): array
    {
        if (empty($this->messages)) {
            return [];
        }

        // Score all messages for relevance to current task
        $scoredMessages = array_map(function (array $message) use ($currentTask): array {
            return [
                'message' => $message,
                'total_score' => $this->calculateTotalScore(message: $message, currentTask: $currentTask),
            ];
        }, $this->messages);

        // Sort by total score (highest first)
        usort($scoredMessages, fn (array $a, array $b): int => $b['total_score'] <=> $a['total_score']);

        // Select messages within token budget
        return $this->selectWithinTokenBudget(scoredMessages: $scoredMessages, tokenBudget: $tokenBudget);
    }

    /**
     * Get recent context (fallback for when no specific task context needed)
     *
     * @return array<array{role: string, content: string}>
     */
    public function getRecentContext(int $limit = 20): array
    {
        return array_map(
            fn (array $msg): array => ['role' => $msg['role'], 'content' => $msg['content']],
            array_slice(array: $this->messages, offset: -$limit)
        );
    }

    /**
     * Get buffer statistics for monitoring
     *
     * @return array{message_count: int, max_size: int, cache_size: int, memory_usage: string}
     */
    public function getStats(): array
    {
        return [
            'message_count' => count($this->messages),
            'max_size' => $this->maxSize,
            'cache_size' => count($this->relevanceCache),
            'memory_usage' => $this->estimateMemoryUsage(),
        ];
    }

    /**
     * Calculate total relevance score using weighted factors
     */
    protected function calculateTotalScore(array $message, string $currentTask): float
    {
        $relevanceScore = $this->calculateRelevance(message: $message, currentTask: $currentTask);
        $recencyScore = $this->calculateRecency(message: $message);
        $importanceScore = $message['importance'];

        // Weighted scoring: 50% relevance + 30% recency + 20% importance
        return ($relevanceScore * 0.5) + ($recencyScore * 0.3) + ($importanceScore * 0.2);
    }

    /**
     * Calculate relevance to current task using keyword and semantic similarity
     */
    protected function calculateRelevance(array $message, string $currentTask): float
    {
        $cacheKey = $message['id'] . ':' . md5($currentTask);
        if (isset($this->relevanceCache[$cacheKey])) {
            return $this->relevanceCache[$cacheKey];
        }

        $content = mb_strtolower($message['content']);
        $task = mb_strtolower($currentTask);

        // Keyword-based similarity (simple but effective)
        $taskWords = array_filter(
            array: explode(separator: ' ', string: $task),
            callback: fn (string $word): bool => mb_strlen($word) > 2
        );
        $matchCount = 0;
        $totalWords = count($taskWords);

        foreach ($taskWords as $word) {
            if (str_contains(haystack: $content, needle: $word)) {
                $matchCount++;
            }
        }

        // Boost score for exact phrase matches
        $exactMatch = str_contains(haystack: $content, needle: $task) ? 0.3 : 0.0;

        // Calculate final relevance score (0-1)
        $keywordScore = $totalWords > 0 ? ($matchCount / $totalWords) : 0.0;
        $relevanceScore = min(1.0, $keywordScore + $exactMatch);

        $this->relevanceCache[$cacheKey] = $relevanceScore;

        return $relevanceScore;
    }

    /**
     * Calculate recency score (newer messages score higher)
     */
    protected function calculateRecency(array $message): float
    {
        $now = time();
        $messageTime = $message['timestamp'];
        $maxAge = 3600; // 1 hour for full recency score

        $age = $now - $messageTime;

        return max(0.0, 1.0 - ($age / $maxAge));
    }

    /**
     * Calculate importance score based on role and content patterns
     */
    protected function calculateImportance(string $role, string $content): float
    {
        $score = 0.5; // Base score

        // Role-based scoring
        $score += match ($role) {
            'system' => 0.3,
            'user' => 0.2,
            'assistant' => 0.1,
            'tool' => -0.2, // Tools less important for context
            'error' => -0.3, // Errors less important
            default => 0.0
        };

        // Content-based scoring
        $content = mb_strtolower($content);

        // Important indicators
        if (str_contains($content, 'error') || str_contains($content, 'fail')) {
            $score += 0.1; // Errors are learning opportunities
        }
        if (str_contains($content, 'implement') || str_contains($content, 'create')) {
            $score += 0.2; // Implementation context is valuable
        }
        if (str_contains($content, 'test') || str_contains($content, 'debug')) {
            $score += 0.15; // Testing context is valuable
        }

        // Length-based adjustment (very short or very long messages less important)
        $length = mb_strlen($content);
        if ($length < 20) {
            $score -= 0.1;
        } elseif ($length > 2000) {
            $score -= 0.05;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Select messages within token budget (approximate)
     *
     * @param array<array{message: array, total_score: float}> $scoredMessages
     *
     * @return array<array{role: string, content: string}>
     */
    protected function selectWithinTokenBudget(array $scoredMessages, int $tokenBudget): array
    {
        $selected = [];
        $estimatedTokens = 0;

        foreach ($scoredMessages as $item) {
            $message = $item['message'];
            $messageTokens = $this->estimateTokens(text: $message['content']);

            if ($estimatedTokens + $messageTokens <= $tokenBudget) {
                $selected[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
                $estimatedTokens += $messageTokens;
            } else {
                break; // Stop when budget exceeded
            }
        }

        return $selected;
    }

    /**
     * Estimate token count (rough approximation: 1 token ≈ 4 characters)
     */
    protected function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Prune messages when buffer is full (remove least important)
     */
    protected function pruneMessages(): void
    {
        // Sort by importance + recency (keep most valuable)
        usort($this->messages, function (array $a, array $b): int {
            $scoreA = $a['importance'] + $this->calculateRecency(message: $a);
            $scoreB = $b['importance'] + $this->calculateRecency(message: $b);

            return $scoreB <=> $scoreA;
        });

        // Keep only the most valuable messages
        $keepCount = (int) ($this->maxSize * 0.8); // Keep 80% when pruning
        $this->messages = array_slice(array: $this->messages, offset: 0, length: $keepCount);

        // Clear relevance cache after pruning
        $this->relevanceCache = [];
    }

    /**
     * Estimate memory usage of buffer
     */
    protected function estimateMemoryUsage(): string
    {
        $bytes = mb_strlen(serialize($this->messages)) + mb_strlen(serialize($this->relevanceCache));

        return $this->formatBytes($bytes);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round(num: $bytes, precision: 2) . ' ' . $units[$i];
    }
}
