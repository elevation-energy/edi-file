<?php

namespace Elevation\EDIFile;

class Transaction
{
    private $group;
    private $content = [];
    private $identifierCode = '';
    private $controlNumber = '';
    private $segments = [];

    const OPENING_SEGMENT = 'ST';
    const CLOSING_SEGMENT = 'SE';

    use Lines;

    public function __construct(Group $group)
    {
        $group->addTransaction($this);
        $this->group = $group;
    }

    public static function fromContent(Group $group, array $content): self
    {
        return (new static($group))
            ->setContent($content)
            ->parseContent();
    }

    public function parseContent(): self
    {
        $elements = explode($this->getTerm('element_delimiter'), $this->content[0]);

        $this->identifierCode = $elements[1];
        $this->controlNumber = $elements[2];

        foreach ($this->content as $data) {
            $shortData = strlen($data) < 3;
            $isTransactionStart = substr($data, 0, 3) == static::OPENING_SEGMENT.$this->getTerm('element_delimiter');
            $isTransactionEnd = substr($data, 0, 3) == static::CLOSING_SEGMENT.$this->getTerm('element_delimiter');

            if ($shortData || $isTransactionStart || $isTransactionEnd) {
                continue;
            }

            $this->segments[] = Segment::fromContent($this, $data);
        }

        return $this;
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getOutput(): string
    {
        $output = $this->getOpeningSegment();

        foreach ($this->getSegments() as $segment) {
            $output .= $segment;
        }

        $output .= $this->getClosingSegment();

        $this->removeDuplicateTerminators($output);

        return $output;
    }

    private function removeDuplicateTerminators(string &$output): void
    {
        $terminator = $this->getTerm('segment_terminator');

        $output = str_replace($terminator.$terminator, $terminator, $output);
    }

    private function getOpeningSegment(): string
    {
        $delim = $this->getTerm('element_delimiter');
        $terminator = $this->getTerm('segment_terminator');

        return static::OPENING_SEGMENT."{$delim}{$this->getEdiType()}{$delim}{$this->getControlNumber()}{$terminator}";
    }

    private function getClosingSegment(): string
    {
        $delim = $this->getTerm('element_delimiter');
        $terminator = $this->getTerm('segment_terminator');
        $numSegments = count($this->getSegments()) + 2;

        return static::CLOSING_SEGMENT."{$delim}{$numSegments}{$delim}{$this->getControlNumber()}{$terminator}";
    }

    public function inspect(): string
    {
        $output = $this->getOutput();
        $term = $this->getTerm('segment_terminator');

        return str_replace($term, $term.PHP_EOL, $output);
    }

    /**
     * @param string $identifierCode The type 814, 867, etc.
     * @param string $controlNumber  The transaction number, usually starts at 0001 and increments
     */
    public function setFields(string $identifierCode, string $controlNumber): void
    {
        $this->identifierCode = $identifierCode;
        $this->controlNumber = $controlNumber;
    }

    /**
     * The ID of the transaction inside the EDIFile
     * Usually starts at 0001 and increments.
     */
    public function getControlNumber(): string
    {
        return $this->controlNumber;
    }

    /**
     * @return Segment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * [
     *     ['BGN' => [data]]
     *     ['N1'  => [data]]
     * ].
     *
     * @param array|callable $segments
     * @param bool           $condition Skips adding the transactions when false
     */
    public function addSegments($segments, ?bool $condition = true): void
    {
        if (false === $condition) {
            return;
        }

        $segs = is_callable($segments) ? $segments() : $segments;

        foreach ($segs as $segment) {
            $name = array_keys($segment)[0];
            $data = array_values($segment)[0];
            $this->addNewSegment($name, $data);
        }
    }

    public function addSegment(SegmentInterface $segment, ?bool $condition = true): void
    {
        $this->addSegments($segment->toArray(), $condition);
    }

    public function repeatSegment(array $values, callable $builder, ?bool $condition = true): void
    {
        foreach ($values as $value) {
            $this->addSegments([$builder($value)], $condition);
        }
    }

    public function addNewSegment(string $name, array $data): void
    {
        $segment = new Segment($this);
        $segment->setFields($name, $data);
        $this->segments[] = $segment;
    }

    /**
     * Returns the type, 814, 867, etc.
     */
    public function getEdiType(): string
    {
        return $this->identifierCode;
    }

    /**
     * Returns a unique ID for the transaction. Used in LIN and other segments.
     */
    public function getReferenceNumber(?string $append = 'LIN'): string
    {
        return implode('', [
            $this->getGroupControlNumber(),
            $this->getControlNumber(),
            $append,
        ]);
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getGroupControlNumber(): string
    {
        return $this->group->getControlNumber();
    }

    public function getTerms(): array
    {
        return $this->group->getTerms();
    }

    public function getTerm(string $term): string
    {
        return $this->group->getTerm($term);
    }

    public function __toString(): string
    {
        return $this->getOutput();
    }
}
