<?php

namespace Elevation\EDIFile;

use DateTime;

class Group
{
    private $edi;
    private $date = null;
    private $time = null;
    private $content = [];
    private $functional_id_code = '';
    private $sender_id_code = '';
    private $receiver_id_code = '';
    private $receiver_gs_id_code;
    private $group_control_number = '';
    private $responsible_agency_code = '';
    private $version_release_identifier = '';
    private $transactions = [];

    public function __construct(EDIFile $edi)
    {
        $this->edi = $edi;
        $edi->addGroup($this);
    }

    public static function fromArray(EDIFile $edi, array $content): self
    {
        return (new static($edi))
            ->setContent($content)
            ->parseContent();
    }

    public static function fromContent(EDIFile $edi, string $content): self
    {
        $terminator = $edi->getTerms()['segment_terminator'];
        $content = explode($terminator, str_replace(PHP_EOL, '', $content));

        return (new static($edi))
            ->setContent($content)
            ->parseContent();
    }

    public function parseContent(): self
    {
        $this->parseFields();
        $this->parseTransactions();

        return $this;
    }

    private function parseFields(): void
    {
        $elements = explode($this->getTerms()['element_delimiter'], $this->content[0]);

        $this->functional_id_code = $elements[1];
        $this->sender_id_code = $elements[2];
        $this->receiver_id_code = $elements[3];
        $this->receiver_gs_id_code = $elements[3];
        $this->date = DateTime::createFromFormat('Ymd', $elements[4]);
        $this->time = DateTime::createFromFormat('Hi', $elements[5]);
        $this->group_control_number = $elements[6];
        $this->responsible_agency_code = $elements[7];
        $this->version_release_identifier = $elements[8];
    }

    private function parseTransactions(): void
    {
        $this->transactions = [];
        $terms = $this->getTerms();

        $transaction = [];
        foreach ($this->content as $data) {
            $isTransactionStart = strlen($data) > 3 && substr($data, 0, 3) == 'ST'.$terms['element_delimiter'];
            $isTransactionEnd = strlen($data) > 3 && substr($data, 0, 3) == 'SE'.$terms['element_delimiter'];

            if ($isTransactionStart) {
                $transaction = [];
            }

            $transaction[] = $data;

            if (false === $isTransactionEnd) {
                continue;
            }

            Transaction::fromContent($this, $transaction);
        }
    }

    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getEdi(): EDIFile
    {
        return $this->edi;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getOutput(): string
    {
        $output = $this->getOpeningSegment();
        $output .= implode('', $this->getTransactions());

        return $output.$this->getClosingSegment();
    }

    private function getOpeningSegment(): string
    {
        $d = $this->getTerm('element_delimiter');
        $groupId = str_pad($this->getFunctionalIDCode(), 2, ' ', STR_PAD_LEFT);

        $output = 'GS'.$d.$groupId.$d.$this->getSenderIDCode().$d;
        $output .= $this->getReceiverGSIDCode().$d;
        $output .= $this->getDate()->format('Ymd').$d.$this->getTime()->format('Hi').$d;
        $output .= $this->getControlNumber().$d;
        $output .= $this->getResponsibleAgencyCode().$d;
        $output .= $this->getVersionReleaseIdentifier().$this->getTerm('segment_terminator');

        return $output;
    }

    public function getClosingSegment(): string
    {
        $d = $this->getTerm('element_delimiter');

        $output = 'GE'.$d.count($this->getTransactions()).$d;

        return $output.$this->getControlNumber().$this->getTerm('segment_terminator');
    }

    public function __toString()
    {
        return $this->getOutput();
    }

    public function setFields(array $fields)
    {
        $fields = array_map(function ($field) {
            return is_string($field) ? trim($field) : $field;
        }, $fields);

        $this->functional_id_code = $fields['functional_id_code'];
        $this->sender_id_code = $fields['sender_id_code'];
        $this->receiver_id_code = $fields['receiver_id_code'];
        $this->receiver_gs_id_code = $fields['receiver_gs_id_code'] ?? $fields['receiver_id_code'];
        $this->group_control_number = $fields['group_control_number'];
        $this->responsible_agency_code = $fields['responsible_agency_code'];
        $this->version_release_identifier = $fields['version_release_identifier'];
        $this->date = $fields['date'];
        $this->time = $fields['time'];
    }

    public function getFunctionalIDCode(): string
    {
        return $this->functional_id_code;
    }

    public function getSenderIDCode(): string
    {
        return $this->sender_id_code;
    }

    public function getReceiverIDCode(): string
    {
        return $this->receiver_id_code;
    }

    /**
     * The receiver_gs_id_code is typically the same as the company interchange ID
     * which is the same as the receiver_id_code.
     *
     * If the company GS ID is different than the interchange ID,
     * you may pass it in as key to setFields($fields), otherwise
     * it will just default to the receiver_id_code.
     */
    public function getReceiverGSIDCode(): string
    {
        return $this->receiver_gs_id_code ?? $this->receiver_id_code;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function getTime(): DateTime
    {
        return is_bool($this->time) ? new DateTime() : $this->time;
    }

    public function getControlNumber(): string
    {
        return $this->group_control_number;
    }

    public function getResponsibleAgencyCode(): string
    {
        return $this->responsible_agency_code;
    }

    public function getVersionReleaseIdentifier(): string
    {
        return $this->version_release_identifier;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getTransactionCount(): int
    {
        return count($this->getTransactions());
    }

    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    public function addNewTransaction($identifierCode, $controlNumber): Transaction
    {
        $trans = new Transaction($this);

        $trans->setFields($identifierCode, $controlNumber);

        return $trans;
    }

    public function getTerms(): array
    {
        return $this->edi->getTerms();
    }

    public function getTerm(string $term): string
    {
        return $this->edi->getTerm($term);
    }
}
