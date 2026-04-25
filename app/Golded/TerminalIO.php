<?php

declare(strict_types=1);

namespace App\Golded;

class TerminalIO
{
    /**
     * Full byte-sequence → key name map.
     *
     * @var array<string, string>
     */
    private const array KEY_MAP = [
        "\033[A" => 'ArrowUp',
        "\033[B" => 'ArrowDown',
        "\033[C" => 'ArrowRight',
        "\033[D" => 'ArrowLeft',
        "\033[5~" => 'PageUp',
        "\033[6~" => 'PageDown',
        "\033[H" => 'Home',
        "\033[F" => 'End',
        "\033[1;3C" => 'Alt+ArrowRight',
        "\033[1;3D" => 'Alt+ArrowLeft',
        "\033j" => 'Alt+j',
        "\033u" => 'Alt+u',
        "\033" => 'Escape',
        "\r" => 'Enter',
        "\n" => 'Enter',
        "\x03" => 'Ctrl+c',
        "\x11" => 'Ctrl+q',
    ];

    private bool $resized = false;

    private ?string $savedStty = null;

    public function rawMode(): void
    {
        $this->savedStty = trim((string) shell_exec('stty -g'));
        system('stty raw -echo');
    }

    public function restore(): void
    {
        if ($this->savedStty !== null && $this->savedStty !== '') {
            system("stty {$this->savedStty}");
        } else {
            system('stty sane');
        }
    }

    public function watchResize(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGWINCH, function (): void {
                $this->resized = true;
            });
        }
    }

    public function resized(): bool
    {
        if ($this->resized) {
            $this->resized = false;
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            return true;
        }

        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        return $this->resized;
    }

    public function readKey(): string
    {
        $bytes = fread(STDIN, 10);

        return $this->parseKey($bytes !== false ? $bytes : '');
    }

    public function width(): int
    {
        $cols = (int) trim((string) shell_exec('tput cols 2>/dev/null'));

        return max($cols, 80);
    }

    public function height(): int
    {
        $lines = (int) trim((string) shell_exec('tput lines 2>/dev/null'));

        return max($lines, 25);
    }

    /**
     * Parse a raw byte string into a named key.
     *
     * Exposed as public so it can be tested in isolation without a real terminal.
     */
    public function parseKey(string $bytes): string
    {
        return self::KEY_MAP[$bytes] ?? $bytes;
    }
}
