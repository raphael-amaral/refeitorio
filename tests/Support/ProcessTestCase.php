<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class ProcessTestCase extends TestCase
{
    protected string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir() . '/refeitorio_test_' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    protected function writeFile(string $relativePath, string $contents): string
    {
        $path = $this->temporaryDirectory . '/' . ltrim($relativePath, '/');
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    protected function writeExecutable(string $relativePath, string $contents): string
    {
        $path = $this->writeFile($relativePath, $contents);
        chmod($path, 0700);

        return $path;
    }

    /**
     * @param list<string>         $command
     * @param array<string,string> $environment
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    protected function runProcess(array $command, string $input = '', array $environment = []): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            array_merge(getenv(), $environment)
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Não foi possível iniciar o processo de teste.');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout'   => $stdout,
            'stderr'   => $stderr,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
