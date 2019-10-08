<?php

namespace Elevation\EDIFile;

use RuntimeException;

class EDIFile
{
    /** @var ISA */
    private $isa;

    /** @var IEA */
    private $iea;

    /** * @var Group[] */
    private $groups = [];

    private $type = '';
    private $segments = [];
    private $content = '';
    private $preamble = '';

    use Lines;
    use Terms;

    /**
     * @var array $params[
     *      'auth_info_qualifier'                => '',
     *      'auth_info'                          => '',
     *      'sec_info_qualifier'                 => '',
     *      'sec_info'                           => '',
     *      'interchange_sender_id_qualifier'    => '',
     *      'interchange_sender_id'              => '',
     *      'interchange_receiver_id_qualifier'  => '',
     *      'interchange_receiver_id'            => '',
     *      'date'                               => '',
     *      'time'                               => '',
     *      'repetition_separator'               => '',
     *      'interchange_control_version_number' => '',
     *      'interchange_control_number'         => '',
     *      'ack_requested'                      => '',
     *      'usage_indicator'                    => '',
     * ]
     */
    public function __construct(array $params = [])
    {
        $this->isa = new ISA($this, $params);
        $this->iea = new IEA($this);
    }

    public static function fromContent(string $content): EDIFile
    {
        return (new static)
            ->setContent($content)
            ->processContent();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getInterchangeControlNumber(): string
    {
        return $this->isa->getInterchangeControlNumber();
    }

    /**
     * Builds segment arrays from the content and the segment terminator
     */
    public function parseSegments(): array
    {
        if (empty($this->content)) {
            throw new \RuntimeException('No content to get segments from');
        }

        $content = trim($this->content, $this->segment_terminator);

        return explode($this->segment_terminator, $content);
    }

    public function processContent(): self
    {
        $this->segments = $this->parseSegments();

        $group = [];
        foreach ($this->segments as $segment) {
            $isGroupStart = strlen($segment) > 3 && substr($segment, 0, 3) == 'GS' . $this->element_delimiter;
            $isGroupEnd = strlen($segment) > 3 && substr($segment, 0, 3) == 'GE' . $this->element_delimiter;

            if ($isGroupStart) {
                $group = [];
            }

            $group[] = $segment;

            if (false === $isGroupEnd) {
                continue;
            }

            Group::fromArray($this, $group);
        }

        return $this;
    }

    /**
     * Returns the EDI Type (814, 997, etc)
     *
     * The type cannot be determined until the EDI has at least
     * one transaction (ST segment). Every ST segment should have
     * the same type within a single EDI file. If the type is not
     * yet set, we just pull it from the first ST segment.
     */
    public function getType(): string
    {
        if (false === empty($this->type)) {
            return $this->type;
        }

        $transactions = $this->getTransactions();

        if (empty($transactions)) {
            throw new \RuntimeException('Cannot determine EDI Type. EDI has no transactions added yet or segment terminators are missing.');
        }

        return $transactions[0]->getEdiType();
    }

    public function getTransactions(): array
    {
        $transactions = array_map(function($group) {
            return $group->getTransactions();
        }, $this->getGroups());

        return array_merge(...$transactions);
    }

    public function getTransactionCount(): int
    {
        return count($this->getTransactions());
    }

    public function getISA(): ?ISA
    {
        return $this->isa;
    }

    public function getControlNumber(): string
    {
        return $this->isa->getInterchangeControlNumber();
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function findGroup($controlNumber): ?Group
    {
        foreach ($this->getGroups() as $group) {
            if ($group->getControlNumber() == $controlNumber) {
                return $group;
            }
        }

        return null;
    }

    public function addGroup(Group $group): void
    {
        $this->groups[] = $group;
    }

    public function addNewGroup(array $fields): Group
    {
        $group = new Group($this, $this->getTerms());

        if (false === empty($fields['functional_id_code'])) {
            $group->setFields($fields);
        }

        return $group;
    }

    public function getRawISA(): string
    {
        $content = trim($this->content, $this->segment_terminator);
        $segments = explode($this->segment_terminator, $content);

        return $segments[0];
    }

    public function getRawIEA(): string
    {
        $content = trim($this->content, $this->segment_terminator);
        $segments = explode($this->segment_terminator, $content);

        return array_reverse($segments)[0];
    }

    public function getOutput(): string
    {
        $output = '';
        $sections = [$this->preamble, $this->isa, $this->groups, $this->iea];

        foreach($sections as $section) {
            if (empty($section)) continue;
            $output .= is_array($section) ? join('', $section) : $section;
        }

        return $output;
    }

    public function inspect(): string
    {
        $output = $this->getOutput();
        $terminator = $this->getTerm('segment_terminator');

        return str_replace($terminator, $terminator.PHP_EOL, $output);
    }

    public function __toString(): string
    {
        return $this->getOutput();
    }

    public function setContent(string $content): self
    {
        $this->validateContent($content);

        $this->preamble = $this->hasPreamble($content) ? $this->extractPreamble($content) : '';
        $this->content = $this->hasPreamble($content) ? $this->stripPreamble($content) : $content;

        $this->setTerms($content);

        $this->isa = ISA::make($this);
        $this->iea = IEA::make($this);

        return $this;
    }

    private function hasPreamble(string $content): bool
    {
        return 0 !== strpos($content, 'ISA');
    }

    private function stripPreamble(string $content): string
    {
        return trim(substr($content, strpos($content, 'ISA')));
    }

    private function extractPreamble(string $content): string
    {
        return trim(substr($content, 0, strpos($content, 'ISA')));
    }

    private function validateContent(string $content): void
    {
        if (false === strpos($content, 'ISA')) {
            throw new RuntimeException('File missing required ISA header');
        }

        if (strlen($content) < 108) {
            throw new RuntimeException('Invalid EDI content length. Content less than 108 chars.');
        }
    }
}
