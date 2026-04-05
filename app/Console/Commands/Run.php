<?php

namespace App\Console\Commands;

use App\Golded\AnsiRenderer;
use App\Golded\GoldedState;
use App\Golded\TerminalIO;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('golded:run')]
#[Description('Run GoldED in the terminal')]
class Run extends Command
{
    public function handle(TerminalIO $io): void
    {
        $io->rawMode();
        $io->watchResize();

        $state = new GoldedState($io->width(), $io->height());
        $renderer = new AnsiRenderer;

        // Clear screen, hide cursor
        echo "\033[2J\033[H\033[?25l";

        try {
            while (true) {
                if ($io->resized()) {
                    $state->resize($io->width(), $io->height());
                }

                echo $renderer->renderScreen(
                    $state->currentScreen(),
                    $state->cols,
                    $state->rows
                );

                $key = $io->readKey();

                if ($key === 'q' || $key === 'Ctrl+q' || $key === 'Ctrl+c') {
                    break;
                }

                $state->handleKey($key);
            }
        } finally {
            // Restore terminal: clear screen, show cursor
            echo "\033[2J\033[H\033[?25h";
            $io->restore();
        }
    }
}
