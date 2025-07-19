# Task Execution Analysis & Deferred Execution Implementation Plan

## Problem Analysis

### Current Behavior
When a user says "add 4 tasks and say hello, we will do the tasks after i say start", the system:

1. Extracts two tasks:
   - Task 1: "say 'hello'"
   - Task 2: "do the tasks after i say start"

2. Immediately executes both tasks sequentially

3. The second task just prompts for input but doesn't actually wait

### Code Flow Analysis

```php
// In CodingAgent::processRequest()
$tasks = $this->extractTasks($userInput);
if (!empty($tasks)) {
    $this->taskManager->addTasks($tasks);
    
    // ISSUE: This loop executes ALL tasks immediately
    while ($currentTask = $this->taskManager->getNextTask()) {
        $this->executeTask($currentTask);
        $this->taskManager->completeCurrentTask();
    }
}
```

### Why This Happens

1. **No Task Type Differentiation**: All tasks are treated as immediate execution
2. **No Execution Context**: Tasks don't carry information about when/how to execute
3. **No Queue Management**: No way to hold tasks for later execution
4. **No Trigger System**: No mechanism to wait for user conditions

## Proposed Solution: Deferred Task Execution

### 1. Extend Task Model

```php
// src/Task/Task.php
enum ExecutionTrigger: string {
    case Immediate = 'immediate';
    case Manual = 'manual';
    case UserInput = 'user_input';
    case Scheduled = 'scheduled';
}

readonly class Task
{
    public function __construct(
        public string $id,
        public string $description,
        public TaskStatus $status,
        public ?string $plan,
        public array $steps,
        public ?DateTimeImmutable $createdAt,
        public ExecutionTrigger $trigger = ExecutionTrigger::Immediate,
        public ?array $triggerData = null, // e.g., ['input' => 'start']
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }
    
    public function isDeferred(): bool
    {
        return $this->trigger !== ExecutionTrigger::Immediate;
    }
    
    public function canExecute(string $context = ''): bool
    {
        return match($this->trigger) {
            ExecutionTrigger::Immediate => true,
            ExecutionTrigger::Manual => false,
            ExecutionTrigger::UserInput => $this->checkUserInputTrigger($context),
            ExecutionTrigger::Scheduled => $this->checkScheduleTrigger(),
        };
    }
    
    private function checkUserInputTrigger(string $input): bool
    {
        if (!isset($this->triggerData['input'])) {
            return false;
        }
        return strtolower(trim($input)) === strtolower($this->triggerData['input']);
    }
}
```

### 2. Update Task Extraction

```php
// In CodingAgent::extractTasks()
protected function extractTasksWithTriggers(string $userInput): array
{
    // Add trigger detection to the structured output schema
    $schema = [
        'tasks' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                    'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                    'execution_trigger' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['immediate', 'manual', 'user_input']],
                            'condition' => ['type' => 'string', 'description' => 'Optional condition like "start"'],
                        ],
                    ],
                ],
            ],
        ],
    ];
    
    // Update prompt to understand deferred execution
    $prompt = PromptTemplates::extractTasksWithTriggers($userInput);
}
```

### 3. Separate Immediate and Deferred Execution

```php
// In CodingAgent::processRequest()
protected function processRequest(string $userInput): AgentResponse
{
    // ... classification logic ...
    
    if ($classification['request_type'] === RequestType::Implementation) {
        $tasks = $this->extractTasksWithTriggers($userInput);
        
        if (!empty($tasks)) {
            $immediateTasks = [];
            $deferredTasks = [];
            
            foreach ($tasks as $task) {
                if ($task->trigger === ExecutionTrigger::Immediate) {
                    $immediateTasks[] = $task;
                } else {
                    $deferredTasks[] = $task;
                }
            }
            
            // Add all tasks to manager
            $this->taskManager->addTasks($tasks);
            
            // Execute only immediate tasks
            if (!empty($immediateTasks)) {
                $this->executeImmediateTasks($immediateTasks);
            }
            
            // Notify about deferred tasks
            if (!empty($deferredTasks)) {
                $message = $this->formatDeferredTasksMessage($deferredTasks);
                return AgentResponse::success($message);
            }
        }
    }
    
    // Check if input triggers any deferred tasks
    $triggeredTasks = $this->taskManager->getTriggeredTasks($userInput);
    if (!empty($triggeredTasks)) {
        return $this->executeDeferredTasks($triggeredTasks);
    }
}
```

### 4. Update TaskManager

```php
// src/Task/TaskManager.php
class TaskManager
{
    public function getDeferredTasks(): array
    {
        return array_filter($this->tasks, fn(Task $task) => 
            $task->isDeferred() && $task->status !== TaskStatus::Completed
        );
    }
    
    public function getTriggeredTasks(string $input): array
    {
        return array_filter($this->getDeferredTasks(), fn(Task $task) => 
            $task->canExecute($input)
        );
    }
    
    public function getImmediateTasks(): array
    {
        return array_filter($this->tasks, fn(Task $task) => 
            !$task->isDeferred() && $task->status === TaskStatus::Planned
        );
    }
}
```

### 5. Add CLI Commands

```php
// In SwarmCLI::handleSpecialCommands()
protected function handleSpecialCommands(string $input): bool
{
    $command = strtolower(trim($input));
    
    switch (true) {
        case $command === '/tasks':
            $this->showTaskQueue();
            return true;
            
        case str_starts_with($command, '/execute'):
            $this->executeCommand($command);
            return true;
            
        case $command === '/clear-tasks':
            $this->clearTasks();
            return true;
            
        // ... existing commands ...
    }
}

protected function showTaskQueue(): void
{
    $tasks = $this->agent->getTaskManager()->getTasks();
    $deferred = array_filter($tasks, fn($t) => $t['trigger'] !== 'immediate');
    
    if (empty($deferred)) {
        $this->ui->showNotification('No deferred tasks in queue', 'info');
        return;
    }
    
    $this->ui->showTaskQueue($deferred);
}
```

## Implementation Steps

### Phase 1: Core Infrastructure (2-3 hours)
1. ✅ Extend Task model with ExecutionTrigger enum
2. ✅ Add trigger-related methods to Task
3. ✅ Update TaskManager with deferred task methods
4. ✅ Add TaskStatus::Deferred status

### Phase 2: AI Integration (2-3 hours)
1. ✅ Update task extraction prompts
2. ✅ Modify extraction schema for triggers
3. ✅ Test trigger detection with various inputs
4. ✅ Update conversation history handling

### Phase 3: Execution Logic (2-3 hours)
1. ✅ Separate immediate/deferred execution paths
2. ✅ Implement trigger checking
3. ✅ Add deferred task notifications
4. ✅ Handle trigger activation

### Phase 4: UI/UX (1-2 hours)
1. ✅ Add /tasks command
2. ✅ Add /execute command
3. ✅ Update TUI to show task queue status
4. ✅ Add visual indicators for deferred tasks

### Phase 5: Testing & Documentation (1-2 hours)
1. ✅ Write tests for deferred execution
2. ✅ Update documentation
3. ✅ Add example use cases
4. ✅ Update CLAUDE.md

## Example Use Cases

### Use Case 1: Conditional Execution
```
User: "Create a backup of the database, but only execute it after I say 'backup now'"
System: Task added to queue. Say 'backup now' when ready to execute.
User: "backup now"
System: Executing backup task...
```

### Use Case 2: Staged Development
```
User: "Set up the project structure, then wait for me to review before adding dependencies"
System: Created project structure. Type '/execute' to continue with dependencies.
User: [reviews files]
User: "/execute"
System: Adding dependencies...
```

### Use Case 3: Mixed Execution
```
User: "Show me the current time (immediate) and prepare a report (wait for 'generate')"
System: Current time: 14:23
System: Report preparation queued. Say 'generate' when ready.
```

## Benefits

1. **User Control**: Users can review before execution
2. **Safety**: Dangerous operations can be deferred
3. **Flexibility**: Mix immediate and deferred tasks
4. **Clarity**: Clear feedback about what will happen when

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Users forget about deferred tasks | Show reminder in prompt, add /tasks command |
| Confusion about triggers | Clear messaging, examples in help |
| Task persistence across sessions | Future: Add task serialization |
| Complex trigger conditions | Start simple, expand later |

## Alternative Approaches Considered

1. **Modal System**: Enter "task mode" vs "execution mode"
   - Rejected: Too complex, breaks natural flow

2. **Explicit Flags**: Use --defer or --wait flags
   - Rejected: Not natural language friendly

3. **Time-based**: Execute after X seconds
   - Rejected: Doesn't match use case

## Conclusion

This implementation provides a natural way to defer task execution while maintaining backward compatibility. The system remains simple for immediate tasks while adding power for complex workflows.