<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

class ChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "prism:chat";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Chat with the application via command line using PrismPHP";

    public function handle(): void
    {
        $this->info("Welcome to the PrismPHP Chat!");
        $this->info("Type 'exit' to quit the chat.");

        while (true) {
            $prompt = $this->ask("You");

            if (strtolower($prompt) === "exit") {
                $this->info("Goodbye!");
                break;
            }

            if (strlen($prompt) > 1000) {
                throw new \Exception("Prompt is too long. Please limit to 1000 characters.");
            }

            // Show thinking indicator
            $this->output->write("<comment>Thinking...</comment>");

            $stream = Prism::text()
                ->using(Provider::Ollama, "gemma3:1b")
                ->withPrompt($prompt)
                ->asStream();

            $fullResponse = "";
            $firstChunk = true;

            foreach ($stream as $event) {
                if ($event instanceof TextDeltaEvent) {
                    if ($firstChunk) {
                        // Clear "Thinking..." and show new status
                        $this->output->write("\r\033[K");
                        $this->output->write("<comment>Generating response...</comment>");
                        $firstChunk = false;
                    }

                    $fullResponse .= $event->delta;

                    // Flush for real-time status
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Show formatting status
            $this->output->write("\r\033[K");
            $this->output->write("<comment>Formatting output...</comment>");

            // Small delay to show the formatting message (optional)
            usleep(100000); // 100ms

            // Clear and render formatted markdown
            $this->output->write("\r\033[K");
            $this->info("Bot:");
            $this->renderMarkdown($fullResponse);
            $this->newLine();
        }
    }

    /**
     * Render markdown text with terminal formatting
     */
    private function renderMarkdown(string $text): void
    {
        $lines = explode("\n", $text);
        $inCodeBlock = false;
        $codeLanguage = "";

        foreach ($lines as $line) {
            // Code block start/end
            if (preg_match("/^```(\w*)/", $line, $matches)) {
                $inCodeBlock = !$inCodeBlock;
                $codeLanguage = $matches[1] ?? "";
                if ($inCodeBlock) {
                    $this->output->writeln(
                        "<fg=gray>┌─" .
                            ($codeLanguage ? " {$codeLanguage} " : "") .
                            str_repeat("─", 40) .
                            "┐</>",
                    );
                } else {
                    $this->output->writeln("<fg=gray>└" . str_repeat("─", 44) . "┘</>");
                }
                continue;
            }

            // Inside code block
            if ($inCodeBlock) {
                $this->output->writeln("<fg=gray>│</> <fg=cyan>{$line}</>");
                continue;
            }

            // Headers
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $headerText = $this->formatInlineMarkdown($matches[2]);
                $this->output->writeln(
                    "<fg=yellow;options=bold>" . str_repeat("▸", $level) . " {$headerText}</>",
                );
                continue;
            }

            // Bullet points
            if (preg_match('/^(\s*)[-*]\s+(.+)$/', $line, $matches)) {
                $indent = $matches[1];
                $content = $this->formatInlineMarkdown($matches[2]);
                $this->output->writeln("{$indent}<fg=green>•</> {$content}");
                continue;
            }

            // Numbered lists
            if (preg_match('/^(\s*)(\d+)\.\s+(.+)$/', $line, $matches)) {
                $indent = $matches[1];
                $number = $matches[2];
                $content = $this->formatInlineMarkdown($matches[3]);
                $this->output->writeln("{$indent}<fg=green>{$number}.</> {$content}");
                continue;
            }

            // Blockquotes
            if (preg_match('/^>\s*(.*)$/', $line, $matches)) {
                $content = $this->formatInlineMarkdown($matches[1]);
                $this->output->writeln("<fg=gray>│</> <fg=white;options=italic>{$content}</>");
                continue;
            }

            // Horizontal rule
            if (preg_match('/^[-*_]{3,}$/', $line)) {
                $this->output->writeln("<fg=gray>" . str_repeat("─", 50) . "</>");
                continue;
            }

            // Regular text with inline formatting
            $this->output->writeln($this->formatInlineMarkdown($line));
        }
    }

    /**
     * Format inline markdown (bold, italic, code)
     */
    private function formatInlineMarkdown(string $text): string
    {
        // Bold text **text**
        $text = preg_replace("/\*\*(.+?)\*\*/", "\033[1m$1\033[0m", $text);

        // Italic text *text* (not preceded/followed by *)
        $text = preg_replace("/(?<!\*)\*([^*]+)\*(?!\*)/", "\033[3m$1\033[0m", $text);

        // Inline code `code`
        $text = preg_replace("/`([^`]+)`/", "\033[36m$1\033[0m", $text);

        return $text;
    }
}
