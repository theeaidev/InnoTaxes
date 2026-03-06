<?php

namespace Tests\Unit;

use App\Services\Aeat\AeatFiscalDataParser;
use Tests\TestCase;

class AeatFiscalDataParserTest extends TestCase
{
    public function test_parser_normalizes_header_and_footer_records_from_the_excel_layout(): void
    {
        /** @var AeatFiscalDataParser $parser */
        $parser = $this->app->make(AeatFiscalDataParser::class);

        $parsed = $parser->parse("0DDFF2025\n999");

        $this->assertSame(2, $parsed['summary']['total_records']);
        $this->assertSame(['0' => 1, '9' => 1], $parsed['summary']['by_type']);
        $this->assertSame(['99' => 1, 'DDFF2025' => 1], $parsed['summary']['by_code']);
        $this->assertSame('DDFF2025', $parsed['records'][0]['record_code']);
        $this->assertSame('registro_de_cabecera', $parsed['records'][0]['layout_key']);
        $this->assertSame('Registro de Cabecera', $parsed['records'][0]['normalized_data']['sheet']);
        $this->assertSame('99', $parsed['records'][1]['record_code']);
        $this->assertSame('Registro de cierre', $parsed['records'][1]['normalized_data']['sheet']);
    }

    public function test_parser_marks_unknown_records_without_crashing(): void
    {
        /** @var AeatFiscalDataParser $parser */
        $parser = $this->app->make(AeatFiscalDataParser::class);

        $parsed = $parser->parse("2ZZZ");

        $this->assertSame(1, $parsed['summary']['total_records']);
        $this->assertNull($parsed['records'][0]['layout_key']);
        $this->assertSame('2', $parsed['records'][0]['record_type']);
        $this->assertStringContainsString('No layout was found', $parsed['records'][0]['warnings'][0]);
    }
}
