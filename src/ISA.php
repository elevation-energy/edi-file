<?php

namespace Elevation\EDIFile;

use DateTime;

class ISA
{
    const RECOGNIZED_CONTROL_VERSION_NUMBERS = ['00200', '00300', '00400', '00401', '00500'];

    private $edi;
    private $date;
    private $time;
    private $auth_info_qualifier = '';
    private $auth_info = '';
    private $sec_info_qualifier = '';
    private $sec_info = '';
    private $interchange_sender_id_qualifier = '';
    private $interchange_sender_id = '';
    private $interchange_receiver_id_qualifier = '';
    private $interchange_receiver_id = '';
    private $repetition_separator = '';
    private $interchange_control_version_number = '';
    private $interchange_control_number = '';
    private $ack_requested = '0';
    private $usage_indicator = '';

    public function __construct(EDIFile $edi, array $fields)
    {
        $this->edi = $edi;

        $this->setFields($fields);
    }

    public static function make(EDIFile $edi): self
    {
        $content = $edi->getRawISA();

        $elements = explode($edi->getTerm('element_delimiter'), $content);

        $fields = [
            'auth_info_qualifier'                 => $elements[1],
            'auth_info'                           => $elements[2],
            'sec_info_qualifier'                  => $elements[3],
            'sec_info'                            => $elements[4],
            'interchange_sender_id_qualifier'     => $elements[5],
            'interchange_sender_id'               => $elements[6],
            'interchange_receiver_id_qualifier'   => $elements[7],
            'interchange_receiver_id'             => $elements[8],
            'date'                                => $elements[9],
            'time'                                => $elements[10],
            'repetition_separator'                => $elements[11],
            'interchange_control_version_number ' => $elements[12],
            'interchange_control_number'          => $elements[13],
            'ack_requested '                      => $elements[14],
            'usage_indicator'                     => $elements[15],
        ];

        return new static($edi, $fields);
    }

    public function getInterchangeSenderID(): string
    {
        return $this->interchange_sender_id;
    }

    public function getInterchangeReceiverID(): string
    {
        return $this->interchange_receiver_id;
    }

    public function getInterchangeControlNumber(): string
    {
        return $this->interchange_control_number;
    }

    public function getOutput(): string
    {
        $elements = [
            'ISA',
            str_pad($this->auth_info_qualifier, 2, '0', STR_PAD_LEFT),
            str_pad($this->auth_info, 10, ' ', STR_PAD_RIGHT),
            str_pad($this->sec_info_qualifier, 2, '0', STR_PAD_LEFT),
            str_pad($this->sec_info, 10, ' ', STR_PAD_RIGHT),
            str_pad($this->interchange_sender_id_qualifier, 2, '0', STR_PAD_LEFT),
            str_pad($this->interchange_sender_id, 15, ' ', STR_PAD_RIGHT),
            str_pad($this->interchange_receiver_id_qualifier, 2, '0', STR_PAD_LEFT),
            str_pad($this->interchange_receiver_id, 15, ' ', STR_PAD_RIGHT),
            $this->date->format('ymd'),
            $this->time->format('Hi'),
            $this->repetition_separator,
            $this->interchange_control_version_number,
            $this->interchange_control_number,
            $this->ack_requested,
            $this->usage_indicator,
            $this->edi->getTerm('sub_element_separator'),
        ];

        return implode($this->edi->getTerm('element_delimiter'), $elements).$this->edi->getTerm('segment_terminator');
    }

    public function __toString(): string
    {
        return $this->getOutput();
    }

    private function setFields(array $fields): void
    {
        $fields = $this->mergeWithDefaults($fields);
        $fields = $this->formatFields($fields);

        foreach ($fields as $propName => $value) {
            $this->$propName = $value;
        }
    }

    private function mergeWithDefaults(array $fields): array
    {
        $fields = array_filter($fields, function ($field) {
            return '' !== $field;
        });

        return array_merge($this->getDefaultFields(), $fields);
    }

    private function formatFields(array $fields): array
    {
        $fields = array_map(function ($field) {
            return is_string($field) ? trim($field) : $field;
        }, $fields);

        $isaControl = $fields['interchange_control_number'];

        $fields['interchange_control_number'] = str_pad($isaControl, 9, '0', STR_PAD_LEFT);
        $fields['date'] = $this->formatDate($fields['date']);
        $fields['time'] = $this->formatTime($fields['time']);

        return $fields;
    }

    private function formatDate($date): DateTime
    {
        if (is_string($date) && 6 == strlen($date)) {
            return DateTime::createFromFormat('ymd', trim($date));
        }

        if ($date instanceof DateTime) {
            return $date;
        }

        throw new \RuntimeException("Invalid date format for ISA. Use 'ymd'. Value provided: $date");
    }

    private function formatTime($date): DateTime
    {
        return $date instanceof DateTime ? $date : DateTime::createFromFormat('Hi', trim($date));
    }

    private function getDefaultFields(): array
    {
        $date = new DateTime();

        return [
            'auth_info_qualifier'                => '0',
            'auth_info'                          => '',
            'sec_info_qualifier'                 => '0',
            'sec_info'                           => '',
            'interchange_sender_id_qualifier'    => '01',
            'interchange_sender_id'              => '',
            'interchange_receiver_id_qualifier'  => '01',
            'interchange_receiver_id'            => null,
            'date'                               => $date,
            'time'                               => $date,
            'repetition_separator'               => 'U',
            'interchange_control_version_number' => '00401',
            'interchange_control_number'         => null,
            'ack_requested'                      => '0',
            'usage_indicator'                    => 'P',
        ];
    }
}
