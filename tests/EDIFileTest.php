<?php

namespace Elevation\EDIFile\Tests;

use Elevation\EDIFile\EDIFile;

class EDIFileTest extends TestCase
{
    /** @test */
    public function it_is_initializable()
    {
        $params = [
            'auth_info_qualifier'                => '',
            'auth_info'                          => '',
            'sec_info_qualifier'                 => '',
            'sec_info'                           => '',
            'interchange_sender_id_qualifier'    => '',
            'interchange_sender_id'              => '',
            'interchange_receiver_id_qualifier'  => '',
            'interchange_receiver_id'            => '',
            'date'                               => '',
            'time'                               => '',
            'repetition_separator'               => '',
            'interchange_control_version_number' => '',
            'interchange_control_number'         => '',
            'ack_requested'                      => '',
            'usage_indicator'                    => '',
        ];

        $ediFile = new EDIFile($params);

        $this->assertInstanceOf(EDIFile::class, $ediFile);
    }

    private function getContent()
    {
        $content = <<<'X12'
ISA*00*          *00*          *01*555058655      *01*116777110      *190423*0000*U*00401*001219885*0*P*^~
GS*GE*555058655*116777110*20190423*0000*1219885*X*004010~
ST*814*0001~
BGN*11*991905914*20120412***1905914~
N1*8S*Utility Provider*1*116777110**40~
N1*SJ*Supplier Name*1*555058655**41~
N1*8R*Customer Name~
LIN*2859044*SH*EL*SH*CE~
ASI*WQ*025~
REF*11*9999191911~
REF*12*8114567801~
DTM*150*20050428~
SE*11*0001~
GE*1*1219885~
IEA*1*001219885~
X12;

        return str_replace(PHP_EOL, '', $content);
    }

    private function beConstructedFromContent(?string $content = null): EDIFile
    {
        $content = $content ?? $this->getContent();

        return EDIFile::fromContent($content);
    }

    /** @test */
    public function it_can_be_constructed_from_string()
    {
        $ediFile = $this->beConstructedFromContent();
        $this->assertInstanceOf(EDIFile::class, $ediFile);
    }

    /** @test */
    public function it_returns_output_that_matches_the_input_when_constructing_from_string()
    {
        $content = $this->getContent();
        $ediFile = $this->beConstructedFromContent();

        $this->assertEquals($content, $ediFile->getOutput());
    }

    /** @test */
    public function it_fails_to_construct_when_the_isa_header_is_missing()
    {
        $failed = false;
        $message = 'File missing required ISA header';

        try {
            $this->beConstructedFromContent('FOO BAR BAZ');
        } catch (\Exception $e) {
            $failed = true;
            $this->assertEquals($message, $e->getMessage());
        }

        $this->assertTrue($failed);
    }

    /** @test */
    public function it_fails_to_construct_when_the_content_length_is_too_short()
    {
        $failed = false;
        $message = 'Invalid EDI content length. Content less than 108 chars.';

        try {
            $this->beConstructedFromContent('ISA*FOO BAR BAZ*QUX~');
        } catch (\Exception $e) {
            $failed = true;
            $this->assertEquals($message, $e->getMessage());
        }

        $this->assertTrue($failed);
    }

    /** @test */
    public function it_derives_the_edi_type_of_the_file_by_looking_at_the_first_transaction()
    {
        $edi = $this->beConstructedFromContent();

        $this->assertEquals('814', $edi->getType());
    }

    /** @test */
    public function it_returns_all_lines_of_the_file()
    {
        $edi = $this->beConstructedFromContent();

        $this->assertCount(15, $edi->getLines());
    }

    /** @test */
    public function it_generates_a_reasonable_filename()
    {
        $edi = $this->beConstructedFromContent();

        $filename = $edi->filename('timestamp-here');

        $this->assertEquals('814_001219885_timestamp-here.x12', $filename);
    }

    /** @test */
    public function it_returns_a_single_line_at_index()
    {
        $edi = $this->beConstructedFromContent();
        $isa = $edi->getLine(0);
        $this->assertEquals('ISA*00*          *00*          *01*555058655      *01*116777110      *190423*0000*U*00401*001219885*0*P*^~', $isa);
    }
}
