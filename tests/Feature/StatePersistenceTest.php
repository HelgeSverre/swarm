<?php

beforeEach(function () {
    // Clean up any existing state file
    $stateFile = getcwd() . '/.swarm.json';
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
});

afterEach(function () {
    // Clean up after tests
    $stateFile = getcwd() . '/.swarm.json';
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }

    // Clean up any backup files
    $backupFiles = glob(getcwd() . '/.swarm.json.corrupt.*');
    if ($backupFiles) {
        foreach ($backupFiles as $file) {
            unlink($file);
        }
    }
});

test('saves state to .swarm.json file', function () {
    $cli = new class
    {
        protected array $syncedState = [];

        public function setSyncedState(array $state): void
        {
            $this->syncedState = $state;
        }

        public function saveState(): void
        {
            $stateFile = getcwd() . '/.swarm.json';
            $json = json_encode($this->syncedState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json !== false) {
                file_put_contents($stateFile, $json);
            }
        }
    };

    // Set some test state
    $testState = [
        'tasks' => [
            ['id' => '1', 'description' => 'Test task'],
        ],
        'current_task' => null,
        'conversation_history' => [
            ['role' => 'user', 'content' => 'Hello', 'timestamp' => 123456],
            ['role' => 'assistant', 'content' => 'Hi there!', 'timestamp' => 123457],
        ],
        'tool_log' => [
            ['tool' => 'read_file', 'status' => 'completed'],
        ],
        'operation' => 'testing',
    ];

    $cli->setSyncedState($testState);
    $cli->saveState();

    // Check file exists
    $stateFile = getcwd() . '/.swarm.json';
    expect(file_exists($stateFile))->toBeTrue();

    // Check content
    $savedState = json_decode(file_get_contents($stateFile), true);
    expect($savedState)->toBe($testState);
});

test('loads state from .swarm.json file', function () {
    $cli = new class
    {
        protected array $syncedState = [
            'tasks' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];

        protected $tui;

        protected $logger = null;

        public function __construct()
        {
            // Mock TUI to avoid notifications
            $this->tui = new class
            {
                public function showNotification($message, $type) {}
            };
        }

        public function getSyncedState(): array
        {
            return $this->syncedState;
        }

        public function loadState(): void
        {
            $stateFile = getcwd() . '/.swarm.json';

            if (file_exists($stateFile)) {
                $json = file_get_contents($stateFile);

                // Handle empty file
                if (empty(mb_trim($json))) {
                    $this->logger?->warning('State file is empty, starting with clean slate');

                    return;
                }

                // Try to decode JSON
                $state = json_decode($json, true);

                // Check for JSON errors
                if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger?->error('Failed to parse state file, starting with clean slate', [
                        'error' => json_last_error_msg(),
                        'file' => $stateFile,
                    ]);

                    // Optionally rename the corrupt file for debugging
                    $backupFile = $stateFile . '.corrupt.' . time();
                    rename($stateFile, $backupFile);
                    $this->logger?->info('Corrupt state file backed up', ['backup' => $backupFile]);

                    $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                    return;
                }

                // Validate state structure
                if (! is_array($state)) {
                    $this->logger?->warning('State file does not contain valid array, starting with clean slate');

                    return;
                }

                // Merge with defaults, ensuring all expected keys exist
                $this->syncedState = array_merge($this->syncedState, $state);

                $this->logger?->info('State loaded from .swarm.json', [
                    'tasks' => count($state['tasks'] ?? []),
                    'history' => count($state['conversation_history'] ?? []),
                ]);
                $this->tui->showNotification('Restored previous session', 'success');
            }
        }
    };

    // Create test state file
    $testState = [
        'tasks' => [
            ['id' => '1', 'description' => 'Loaded task'],
        ],
        'current_task' => ['id' => '1', 'description' => 'Current task'],
        'conversation_history' => [
            ['role' => 'user', 'content' => 'Previous message', 'timestamp' => 123456],
        ],
        'tool_log' => [
            ['tool' => 'write_file', 'status' => 'completed'],
        ],
        'operation' => 'loaded_operation',
    ];

    $stateFile = getcwd() . '/.swarm.json';
    file_put_contents($stateFile, json_encode($testState, JSON_PRETTY_PRINT));

    // Load state
    $cli->loadState();

    // Check loaded state
    $loadedState = $cli->getSyncedState();
    expect($loadedState)->toBe($testState);
});

test('handles missing state file gracefully', function () {
    $cli = new class
    {
        protected array $syncedState = [
            'tasks' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];

        protected $tui;

        protected $logger = null;

        public function __construct()
        {
            // Mock TUI
            $this->tui = new class
            {
                public function showNotification($message, $type) {}
            };
        }

        public function getSyncedState(): array
        {
            return $this->syncedState;
        }

        public function loadState(): void
        {
            $stateFile = getcwd() . '/.swarm.json';

            if (file_exists($stateFile)) {
                $json = file_get_contents($stateFile);

                // Handle empty file
                if (empty(mb_trim($json))) {
                    $this->logger?->warning('State file is empty, starting with clean slate');

                    return;
                }

                // Try to decode JSON
                $state = json_decode($json, true);

                // Check for JSON errors
                if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger?->error('Failed to parse state file, starting with clean slate', [
                        'error' => json_last_error_msg(),
                        'file' => $stateFile,
                    ]);

                    // Optionally rename the corrupt file for debugging
                    $backupFile = $stateFile . '.corrupt.' . time();
                    rename($stateFile, $backupFile);
                    $this->logger?->info('Corrupt state file backed up', ['backup' => $backupFile]);

                    $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                    return;
                }

                // Validate state structure
                if (! is_array($state)) {
                    $this->logger?->warning('State file does not contain valid array, starting with clean slate');

                    return;
                }

                // Merge with defaults, ensuring all expected keys exist
                $this->syncedState = array_merge($this->syncedState, $state);

                $this->logger?->info('State loaded from .swarm.json', [
                    'tasks' => count($state['tasks'] ?? []),
                    'history' => count($state['conversation_history'] ?? []),
                ]);
                $this->tui->showNotification('Restored previous session', 'success');
            }
        }
    };

    // Ensure no state file exists
    $stateFile = getcwd() . '/.swarm.json';
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }

    // Load state (should not throw)
    $cli->loadState();

    // State should remain default
    $loadedState = $cli->getSyncedState();
    expect($loadedState)->toBe([
        'tasks' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ]);
});

test('handles corrupt JSON gracefully', function () {
    $cli = new class
    {
        public $notificationShown = false;

        public $notificationMessage = '';

        protected array $syncedState = [
            'tasks' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];

        protected $tui;

        protected $logger = null;

        public function __construct()
        {
            // Mock TUI
            $this->tui = new class
            {
                public $parent;

                public function showNotification($message, $type)
                {
                    $this->parent->notificationShown = true;
                    $this->parent->notificationMessage = $message;
                }
            };
            $this->tui->parent = $this;
        }

        public function getSyncedState(): array
        {
            return $this->syncedState;
        }

        public function loadState(): void
        {
            $stateFile = getcwd() . '/.swarm.json';

            if (file_exists($stateFile)) {
                $json = file_get_contents($stateFile);

                // Handle empty file
                if (empty(mb_trim($json))) {
                    $this->logger?->warning('State file is empty, starting with clean slate');

                    return;
                }

                // Try to decode JSON
                $state = json_decode($json, true);

                // Check for JSON errors
                if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger?->error('Failed to parse state file, starting with clean slate', [
                        'error' => json_last_error_msg(),
                        'file' => $stateFile,
                    ]);

                    // Optionally rename the corrupt file for debugging
                    $backupFile = $stateFile . '.corrupt.' . time();
                    rename($stateFile, $backupFile);
                    $this->logger?->info('Corrupt state file backed up', ['backup' => $backupFile]);

                    $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                    return;
                }

                // Validate state structure
                if (! is_array($state)) {
                    $this->logger?->warning('State file does not contain valid array, starting with clean slate');

                    return;
                }

                // Merge with defaults, ensuring all expected keys exist
                $this->syncedState = array_merge($this->syncedState, $state);

                $this->logger?->info('State loaded from .swarm.json', [
                    'tasks' => count($state['tasks'] ?? []),
                    'history' => count($state['conversation_history'] ?? []),
                ]);
                $this->tui->showNotification('Restored previous session', 'success');
            }
        }
    };

    // Create corrupt JSON file
    $stateFile = getcwd() . '/.swarm.json';
    file_put_contents($stateFile, '{"invalid json: true}');

    // Load state (should not throw)
    $cli->loadState();

    // State should remain default
    $loadedState = $cli->getSyncedState();
    expect($loadedState)->toBe([
        'tasks' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ]);

    // Check that notification was shown
    expect($cli->notificationShown)->toBeTrue();
    expect($cli->notificationMessage)->toBe('State file was corrupt, starting fresh');

    // Check backup file was created
    $backupFiles = glob(getcwd() . '/.swarm.json.corrupt.*');
    expect($backupFiles)->toHaveCount(1);
});

test('handles empty state file gracefully', function () {
    $cli = new class
    {
        protected array $syncedState = [
            'tasks' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];

        protected $tui;

        protected $logger = null;

        public function __construct()
        {
            // Mock TUI
            $this->tui = new class
            {
                public function showNotification($message, $type) {}
            };
        }

        public function getSyncedState(): array
        {
            return $this->syncedState;
        }

        public function loadState(): void
        {
            $stateFile = getcwd() . '/.swarm.json';

            if (file_exists($stateFile)) {
                $json = file_get_contents($stateFile);

                // Handle empty file
                if (empty(mb_trim($json))) {
                    $this->logger?->warning('State file is empty, starting with clean slate');

                    return;
                }

                // Try to decode JSON
                $state = json_decode($json, true);

                // Check for JSON errors
                if ($state === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger?->error('Failed to parse state file, starting with clean slate', [
                        'error' => json_last_error_msg(),
                        'file' => $stateFile,
                    ]);

                    // Optionally rename the corrupt file for debugging
                    $backupFile = $stateFile . '.corrupt.' . time();
                    rename($stateFile, $backupFile);
                    $this->logger?->info('Corrupt state file backed up', ['backup' => $backupFile]);

                    $this->tui->showNotification('State file was corrupt, starting fresh', 'warning');

                    return;
                }

                // Validate state structure
                if (! is_array($state)) {
                    $this->logger?->warning('State file does not contain valid array, starting with clean slate');

                    return;
                }

                // Merge with defaults, ensuring all expected keys exist
                $this->syncedState = array_merge($this->syncedState, $state);

                $this->logger?->info('State loaded from .swarm.json', [
                    'tasks' => count($state['tasks'] ?? []),
                    'history' => count($state['conversation_history'] ?? []),
                ]);
                $this->tui->showNotification('Restored previous session', 'success');
            }
        }
    };

    // Create empty file
    $stateFile = getcwd() . '/.swarm.json';
    file_put_contents($stateFile, '');

    // Load state (should not throw)
    $cli->loadState();

    // State should remain default
    $loadedState = $cli->getSyncedState();
    expect($loadedState)->toBe([
        'tasks' => [],
        'current_task' => null,
        'conversation_history' => [],
        'tool_log' => [],
        'operation' => '',
    ]);
});

test('saveStateOnShutdown only saves when there is state', function () {
    $cli = new class
    {
        public bool $saveStateCalled = false;

        protected array $syncedState = [
            'tasks' => [],
            'current_task' => null,
            'conversation_history' => [],
            'tool_log' => [],
            'operation' => '',
        ];

        public function saveStateOnShutdown(): void
        {
            // Only save if we have some state to save
            if (! empty($this->syncedState['conversation_history']) ||
                ! empty($this->syncedState['tasks']) ||
                ! empty($this->syncedState['tool_log'])) {
                $this->saveState();
            }
        }

        public function setSyncedState(array $state): void
        {
            $this->syncedState = $state;
        }

        protected function saveState(): void
        {
            $this->saveStateCalled = true;
            // Don't actually save to file in tests
        }
    };

    // Test with empty state (should not save)
    $cli->saveStateOnShutdown();
    expect($cli->saveStateCalled)->toBeFalse();

    // Test with some conversation history (should save)
    $cli->setSyncedState([
        'tasks' => [],
        'current_task' => null,
        'conversation_history' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
        'tool_log' => [],
        'operation' => '',
    ]);
    $cli->saveStateCalled = false;
    $cli->saveStateOnShutdown();
    expect($cli->saveStateCalled)->toBeTrue();
});
