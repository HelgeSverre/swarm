# Task Management System Updates

## Task (`src/Task/Task.php`)
Immutable value object representing tasks:
- Unique ID and description
- Status tracking with TaskStatus enum
- Plan and steps storage
- Timestamps for tracking:
  - `createdAt`: When the task was created
  - `completedAt`: When the task was completed (new)
- Execution time calculation
- Immutable state transitions

### Key Methods:
- `create()`: Create new pending task
- `fromArray()`: Restore task from state
- `toArray()`: Serialize task for persistence
- `withPlan()`: Add execution plan
- `startExecuting()`: Transition to executing
- `complete()`: Mark as completed with timestamp
- Status checkers: `isCompleted()`, `isExecuting()`, etc.

## Task History Persistence

### Features:
1. **Automatic Archival**: Completed tasks automatically moved to `task_history`
2. **History Limit**: Maintains last 1000 completed tasks
3. **Execution Tracking**: Records completion time and execution duration
4. **State Persistence**: Survives across sessions
5. **Backward Compatibility**: Old state files automatically migrated

### Implementation Details:
- When tasks complete, they're moved from `tasks` to `task_history`
- Each completed task includes:
  - Original task data (id, description, plan, steps)
  - `created_at` timestamp
  - `completed_at` timestamp (added on completion)
  - `execution_time` (calculated from timestamps)
- History maintained at 1000 tasks via FIFO (oldest removed first)

### State File Structure:
```json
{
  "tasks": [/* active tasks */],
  "task_history": [/* completed tasks with timestamps */],
  "current_task": null,
  "conversation_history": {},
  "tool_log": []
}
```

This update ensures all completed work is tracked for future reference while keeping the active task list clean and manageable.