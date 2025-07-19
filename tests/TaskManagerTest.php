<?php

use HelgeSverre\Swarm\Task\TaskManager;
use PHPUnit\Framework\TestCase;

class TaskManagerTest extends TestCase
{
    public function testAddTaskAddsTaskToList()
    {
        $taskManager = new TaskManager;
        $description = 'Test task';
        $taskManager->addTask($description);

        $tasks = $taskManager->getTasks();

        $this->assertCount(1, $tasks);
        $this->assertEquals($description, $tasks[0]->description);
        $this->assertEquals('pending', $tasks[0]->status->value);
    }

    public function testAddTaskWithEmptyDescriptionDoesNotAdd()
    {
        $taskManager = new TaskManager;
        $taskManager->addTask('');

        $tasks = $taskManager->getTasks();

        $this->assertCount(0, $tasks);
    }

    public function testAddMultipleTasks()
    {
        $taskManager = new TaskManager;
        $taskManager->addTask('Task 1');
        $taskManager->addTask('Task 2');

        $tasks = $taskManager->getTasks();

        $this->assertCount(2, $tasks);
        $this->assertEquals('Task 1', $tasks[0]->description);
        $this->assertEquals('Task 2', $tasks[1]->description);
    }
}
