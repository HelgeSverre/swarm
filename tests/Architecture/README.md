# Architectural Tests for Swarm

This directory contains Pest architectural tests that enforce code quality, consistency, and architectural patterns across the Swarm codebase.

## Test Categories

### 1. **ArchitectureTest.php** - Layer Boundaries
- Enforces separation between layers (CLI, Agent, Core, Tools, Task)
- Prevents inappropriate dependencies between layers
- Ensures proper namespace organization
- Validates class placement matches directory structure

### 2. **ToolArchitectureTest.php** - Tool-specific Rules
- All tools must extend the abstract `Tool` class
- Tools should be self-contained (no cross-tool dependencies)
- Enforces proper method visibility and signatures
- Prevents use of global state in tools
- Validates tool attribute organization

### 3. **CodeStyleTest.php** - PHP 8.3 Standards
- Enforces use of `protected` over `private` visibility
- Validates proper class structure
- Ensures proper naming conventions (e.g., Exception suffix)
- Enforces PSR-4 autoloading standards
- Uses PHP preset for deprecated function detection

### 4. **DependencyTest.php** - Dependency Injection
- Enforces constructor injection patterns
- Ensures logger is always nullable
- Prevents service locator anti-pattern
- Validates dependency inversion principle
- Prevents hard-coded instantiation in business logic

### 5. **ValueObjectTest.php** - Immutability Rules
- Ensures response objects are immutable
- Validates static factory method usage
- Prevents service dependencies in value objects
- Enforces proper method exposure
- Ensures value objects are self-contained

## Running Tests

Run all architectural tests:
```bash
./vendor/bin/pest tests/Architecture
```

Run specific test file:
```bash
./vendor/bin/pest tests/Architecture/ToolArchitectureTest.php
```

## Key Architectural Decisions

1. **Layer Separation**: Clear boundaries between presentation (CLI), application (Agent), domain (Task/Router), and infrastructure (Tools) layers.

2. **Tool Independence**: Tools are self-contained units that don't depend on each other, except Search tool which needs router for sub-searches.

3. **Dependency Injection**: All dependencies injected via constructor, with logger always optional.

4. **Value Objects**: Response objects use static factory methods and are immutable.

5. **Protected Visibility**: Project preference for `protected` over `private` for better extensibility.

## Adding New Tests

When adding new architectural rules:

1. Identify the architectural constraint to enforce
2. Choose the appropriate test file based on category
3. Write descriptive test names that explain the rule
4. Use `ignoring()` for legitimate exceptions
5. Document why exceptions are allowed

## Benefits

- **Early Detection**: Catch architectural violations during development
- **Documentation**: Tests serve as living documentation of architectural decisions
- **Consistency**: Enforce consistent patterns across the codebase
- **Refactoring Safety**: Prevent breaking architectural rules during refactoring
- **Team Alignment**: Clear rules help team members understand expectations