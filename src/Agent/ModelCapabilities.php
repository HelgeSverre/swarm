<?php

declare(strict_types=1);

namespace HelgeSverre\Swarm\Agent;

/**
 * Static helper encapsulating model-specific API knowledge.
 *
 * Single source of truth for which parameters each model family supports.
 * GPT-5 reasoning models do NOT accept temperature but support reasoning_effort and verbosity.
 * The non-reasoning variant gpt-5-chat-latest behaves like GPT-4.
 */
class ModelCapabilities
{
    /**
     * Reasoning model prefixes that do NOT support temperature.
     */
    protected const REASONING_PREFIXES = ['gpt-5', 'o1', 'o3', 'o4-mini'];

    /**
     * Explicit non-reasoning models that share a prefix with reasoning models.
     */
    protected const NON_REASONING_EXCEPTIONS = ['gpt-5-chat-latest'];

    /**
     * Prefixes that support the verbosity parameter.
     */
    protected const VERBOSITY_PREFIXES = ['gpt-5'];

    public static function isReasoningModel(string $model): bool
    {
        // Check explicit exceptions first
        foreach (self::NON_REASONING_EXCEPTIONS as $exception) {
            if ($model === $exception || str_starts_with($model, $exception)) {
                return false;
            }
        }

        foreach (self::REASONING_PREFIXES as $prefix) {
            if ($model === $prefix || str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function supportsTemperature(string $model): bool
    {
        return ! self::isReasoningModel($model);
    }

    public static function supportsReasoningEffort(string $model): bool
    {
        return self::isReasoningModel($model);
    }

    public static function supportsVerbosity(string $model): bool
    {
        // Check exceptions first (gpt-5-chat-latest is NOT a reasoning model but IS gpt-5 family)
        foreach (self::NON_REASONING_EXCEPTIONS as $exception) {
            if ($model === $exception || str_starts_with($model, $exception)) {
                return false;
            }
        }

        foreach (self::VERBOSITY_PREFIXES as $prefix) {
            if ($model === $prefix || str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the correct API request options for the given model.
     *
     * Strips unsupported parameters and adds model-specific ones.
     *
     * @param string $model The model identifier (e.g. gpt-5-mini, gpt-4o-mini)
     * @param array $messages Chat messages array
     * @param array $options Caller-provided options (may include temperature, tools, response_format, etc.)
     * @param string $reasoningEffort Default reasoning effort for reasoning models
     * @param string $verbosity Default verbosity for GPT-5 models
     */
    public static function buildRequestOptions(
        string $model,
        array $messages,
        array $options = [],
        string $reasoningEffort = 'medium',
        string $verbosity = 'medium',
    ): array {
        $requestOptions = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $options['max_completion_tokens'] ?? 4000,
        ];

        // Temperature: only for non-reasoning models
        if (self::supportsTemperature($model)) {
            $requestOptions['temperature'] = $options['temperature'] ?? 0.7;
        }

        // Reasoning effort: only for reasoning models
        if (self::supportsReasoningEffort($model)) {
            $requestOptions['reasoning_effort'] = $options['reasoning_effort'] ?? $reasoningEffort;
        }

        // Verbosity: only for GPT-5 family
        if (self::supportsVerbosity($model)) {
            $requestOptions['verbosity'] = $options['verbosity'] ?? $verbosity;
        }

        // Pass through other supported options
        $passthroughKeys = ['tools', 'response_format', 'tool_choice'];
        foreach ($passthroughKeys as $key) {
            if (isset($options[$key])) {
                $requestOptions[$key] = $options[$key];
            }
        }

        return $requestOptions;
    }
}
