<?php

namespace HelgeSverre\Swarm\CLI\Activity;

use HelgeSverre\Swarm\Enums\CLI\ActivityType;
use HelgeSverre\Swarm\Enums\CLI\NotificationType;

/**
 * Represents a notification activity entry
 *
 * System notifications, errors, and other messages that aren't
 * part of the conversation or tool calls
 */
class NotificationEntry extends ActivityEntry
{
    public function __construct(
        public readonly string $message,
        public readonly NotificationType $notificationType,
        int $timestamp,
    ) {
        parent::__construct(ActivityType::Notification, $timestamp);
    }

    /**
     * Get the formatted message for display
     */
    public function getMessage(): string
    {
        // Notifications already include their icon in the message
        return $this->message;
    }

    /**
     * Override to use notification type's color instead of activity type's color
     */
    public function getColor(): \HelgeSverre\Swarm\Enums\CLI\AnsiColor
    {
        return $this->notificationType->getThemeColor()->getAnsiColor();
    }

    /**
     * Create an error notification
     */
    public static function error(string $message, int $timestamp): self
    {
        return new self($message, NotificationType::Error, $timestamp);
    }

    /**
     * Create a success notification
     */
    public static function success(string $message, int $timestamp): self
    {
        return new self($message, NotificationType::Success, $timestamp);
    }

    /**
     * Create a warning notification
     */
    public static function warning(string $message, int $timestamp): self
    {
        return new self($message, NotificationType::Warning, $timestamp);
    }

    /**
     * Create an info notification
     */
    public static function info(string $message, int $timestamp): self
    {
        return new self($message, NotificationType::Info, $timestamp);
    }
}
