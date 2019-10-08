<?php

namespace Elevation\EDIFile;

trait Lines
{
    public function getLines(): array
    {
        $output = trim($this->getOutput(), $this->getTerm('segment_terminator'));
        $output = str_replace($this->getTerm('segment_terminator'), $this->getTerm('segment_terminator').PHP_EOL, $output);
        $output = str_replace(PHP_EOL.PHP_EOL, PHP_EOL, $output);

        return explode(PHP_EOL, $output);
    }

    public function getLine(int $index, $print = false): ?string
    {
        if ($print) {
            echo $this->getLines()[$index] ?? '';
            echo PHP_EOL;
        }

        return $this->getLines()[$index] ?? null;
    }

    abstract public function getOutput(): string;

    abstract public function getTerm(string $term): string;
}
