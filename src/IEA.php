<?php

namespace Elevation\EDIFile;

class IEA
{
    private $edi;

    public function __construct(EDIFile $edi)
    {
        $this->edi = $edi;
    }

    public static function make(EDIFile $edi): self
    {
        return new static($edi);
    }

    public function __toString()
    {
        $delimiter = $this->edi->getTerm('element_delimiter');
        $numGroups = count($this->edi->getGroups());
        $controlNumber = str_pad($this->edi->getControlNumber(), 9, '0', STR_PAD_LEFT);
        $terminator = $this->edi->getTerm('segment_terminator');

        return 'IEA'.$delimiter.$numGroups.$delimiter.$controlNumber.$terminator;
    }
}
