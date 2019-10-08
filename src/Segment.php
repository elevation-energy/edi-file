<?php

namespace Elevation\EDIFile;

class Segment
{
    private $transaction;
    private $content = '';
    private $identifier = '';
    private $data = [];

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public static function fromContent(Transaction $transaction, string $content): self
    {
        return (new static($transaction))
            ->setContent($content)
            ->parseContent()
        ;
    }

    public function getTerm(string $term): string
    {
        return $this->transaction->getTerm($term);
    }

    public function parseContent(): self
    {
        $elements = explode($this->getTerm('element_delimiter'), $this->content);
        $this->identifier = array_shift($elements);
        $this->data = $elements;

        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getOutput(): string
    {
        $delim = $this->getTerm('element_delimiter');

        $output = $this->getIdentifier().$delim;
        $output .= join($delim, $this->getData());

        $output = rtrim($output, $delim);
        $output = rtrim($output, $this->getTerm('segment_terminator'));

        return $output.$this->getTerm('segment_terminator');
    }

    public function setFields(string $segment_name, array $segment_data): void
    {
        $this->identifier = $segment_name;
        $this->data = $segment_data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getData(?int $index = null)
    {
        $value = null === $index ? $this->data : $this->data[$index];

        if (is_string($value)) {
            return rtrim($value, $this->getTerm('segment_terminator'));
        }

        return $value;
    }

    public function __toString(): string
    {
        return $this->getOutput();
    }
}
