<?php

use HelgeSverre\Swarm\Core\PathChecker;
use HelgeSverre\Swarm\Exceptions\PathNotAllowedException;

beforeEach(function () {
    $this->projectPath = dirname(__DIR__, 3); // Project root
    $this->tempDir = sys_get_temp_dir() . '/swarm_test_' . uniqid();
    mkdir($this->tempDir);

    $this->pathChecker = new PathChecker($this->projectPath);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

test('constructor throws exception for non-existent project path', function () {
    expect(fn () => new PathChecker('/non/existent/path'))
        ->toThrow(InvalidArgumentException::class, 'Project path does not exist');
});

test('allows paths within project directory', function () {
    expect($this->pathChecker->isAllowed($this->projectPath . '/src/Core/PathChecker.php'))->toBeTrue();
    expect($this->pathChecker->isAllowed($this->projectPath . '/tests'))->toBeTrue();
    expect($this->pathChecker->isAllowed($this->projectPath))->toBeTrue();
});

test('denies paths outside project directory by default', function () {
    expect($this->pathChecker->isAllowed('/etc/passwd'))->toBeFalse();
    expect($this->pathChecker->isAllowed('/tmp'))->toBeFalse();
    expect($this->pathChecker->isAllowed($this->tempDir))->toBeFalse();
});

test('allows relative paths within project', function () {
    expect($this->pathChecker->isAllowed('src/Core/PathChecker.php'))->toBeTrue();
    expect($this->pathChecker->isAllowed('tests'))->toBeTrue();
    expect($this->pathChecker->isAllowed('./composer.json'))->toBeTrue();
});

test('can add allowed paths', function () {
    expect($this->pathChecker->isAllowed($this->tempDir))->toBeFalse();

    $added = $this->pathChecker->addAllowedPath($this->tempDir);
    expect($added)->toBeTrue();
    expect($this->pathChecker->isAllowed($this->tempDir))->toBeTrue();
});

test('cannot add non-existent paths', function () {
    $result = $this->pathChecker->addAllowedPath('/non/existent/path');
    expect($result)->toBeFalse();
});

test('cannot add files as allowed paths', function () {
    $file = $this->projectPath . '/composer.json';
    $result = $this->pathChecker->addAllowedPath($file);
    expect($result)->toBeFalse();
});

test('can remove allowed paths', function () {
    $this->pathChecker->addAllowedPath($this->tempDir);
    expect($this->pathChecker->isAllowed($this->tempDir))->toBeTrue();

    $removed = $this->pathChecker->removeAllowedPath($this->tempDir);
    expect($removed)->toBeTrue();
    expect($this->pathChecker->isAllowed($this->tempDir))->toBeFalse();
});

test('removing non-existent path returns false', function () {
    $result = $this->pathChecker->removeAllowedPath('/non/existent/path');
    expect($result)->toBeFalse();
});

test('validatePath returns resolved path for allowed paths', function () {
    $resolved = $this->pathChecker->validatePath('composer.json');
    expect($resolved)->toBe($this->projectPath . '/composer.json');
});

test('validatePath throws exception for non-existent paths', function () {
    expect(fn () => $this->pathChecker->validatePath('/non/existent/file'))
        ->toThrow(PathNotAllowedException::class, 'Path does not exist or is not accessible');
});

test('validatePath throws exception for denied paths', function () {
    expect(fn () => $this->pathChecker->validatePath('/etc/passwd'))
        ->toThrow(PathNotAllowedException::class, 'Path access denied');
});

test('getAllowedPaths returns only explicitly added paths', function () {
    expect($this->pathChecker->getAllowedPaths())->toBe([]);

    $this->pathChecker->addAllowedPath($this->tempDir);
    $allowedPaths = $this->pathChecker->getAllowedPaths();

    expect($allowedPaths)->toHaveCount(1);
    expect($allowedPaths[0])->toBe(realpath($this->tempDir));
});

test('getProjectPath returns normalized project path', function () {
    $projectPath = $this->pathChecker->getProjectPath();
    expect($projectPath)->toBe($this->projectPath);
    expect($projectPath)->not->toEndWith('/');
});

test('setAllowedPaths replaces existing allowed paths', function () {
    $tempDir2 = sys_get_temp_dir() . '/swarm_test2_' . uniqid();
    mkdir($tempDir2);

    try {
        $this->pathChecker->addAllowedPath($this->tempDir);
        expect($this->pathChecker->getAllowedPaths())->toHaveCount(1);

        $this->pathChecker->setAllowedPaths([$tempDir2]);
        $allowedPaths = $this->pathChecker->getAllowedPaths();

        expect($allowedPaths)->toHaveCount(1);
        expect($allowedPaths[0])->toBe(realpath($tempDir2));
        expect($this->pathChecker->isAllowed($this->tempDir))->toBeFalse();
        expect($this->pathChecker->isAllowed($tempDir2))->toBeTrue();
    } finally {
        rmdir($tempDir2);
    }
});

test('handles path traversal attempts', function () {
    $traversalPath = $this->projectPath . '/../../../etc/passwd';
    expect($this->pathChecker->isAllowed($traversalPath))->toBeFalse();
});

test('handles symbolic links correctly', function () {
    $linkPath = $this->tempDir . '/symlink';
    $targetFile = $this->projectPath . '/composer.json';

    symlink($targetFile, $linkPath);

    try {
        // Symlink to project file should be allowed
        expect($this->pathChecker->isAllowed($linkPath))->toBeTrue();

        // Create symlink outside project
        $outsideLink = $this->tempDir . '/outside_link';
        symlink('/etc/passwd', $outsideLink);

        // Symlink to outside file should be denied
        expect($this->pathChecker->isAllowed($outsideLink))->toBeFalse();

        unlink($outsideLink);
    } finally {
        unlink($linkPath);
    }
});

test('prevents adding duplicate allowed paths', function () {
    $this->pathChecker->addAllowedPath($this->tempDir);
    $this->pathChecker->addAllowedPath($this->tempDir);

    $allowedPaths = $this->pathChecker->getAllowedPaths();
    expect($allowedPaths)->toHaveCount(1);
});
