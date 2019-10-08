<?php

namespace Elevation\EDIFile;

trait Terms
{
    private $element_delimiter = '*';
    private $sub_element_separator = '^';
    private $segment_terminator = '~';

    public function getTerms(): array
    {
        return [
            'element_delimiter'     => $this->element_delimiter,
            'sub_element_separator' => $this->sub_element_separator,
            'segment_terminator'    => $this->segment_terminator,
        ];
    }

    public function getTerm(string $term): string
    {
        return $this->getTerms()[$term];
    }

    /**
     * Set the terms by analyzing the ISA header
     *
     * According to the standard the ISA should ALWAYS be 106 characters.
     */
    public function setTerms(string $content)
    {
        $content = $this->stripPreamble($content);
        $terms = $this->deriveTerms($content);

        $this->element_delimiter = $terms['element_delimiter'];
        $this->sub_element_separator = $terms['sub_element_separator'];
        $this->segment_terminator = $terms['segment_terminator'];
    }

    /**
     * Attempts to determine the terms by analyzing the ISA header
     */
    private function deriveTerms(string $content): array
    {
        $pieces = explode(PHP_EOL, $content);
        $endOfLineIsSegmentTerminator = strlen($pieces[0]) == 105;
        $terminator = $endOfLineIsSegmentTerminator ? PHP_EOL : substr($content, 105, 1);

        return [
            'element_delimiter'     => substr($content, 103, 1),
            'sub_element_separator' => substr($content, 104, 1),
            'segment_terminator'    => $terminator,
        ];
    }
}
