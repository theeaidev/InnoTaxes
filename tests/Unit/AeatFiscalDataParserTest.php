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

    public function test_parser_prefers_the_specific_rta_layout_over_generic_rt_prefixes(): void
    {
        /** @var AeatFiscalDataParser $parser */
        $parser = $this->app->make(AeatFiscalDataParser::class);

        $parsed = $parser->parse('2RTA0001');

        $this->assertSame('rta_rdto_trabajo_mod_190', $parsed['records'][0]['layout_key']);
        $this->assertSame('RTA0001', $parsed['records'][0]['record_code']);
        $this->assertSame('RTA - Rdto. trabajo mod 190', $parsed['records'][0]['normalized_data']['sheet']);
    }

    public function test_parser_infers_domestic_dom_variant_when_layout_value_is_missing(): void
    {
        /** @var AeatFiscalDataParser $parser */
        $parser = $this->app->make(AeatFiscalDataParser::class);

        $parsed = $parser->parse('2DOM0001 20');

        $this->assertSame('dom_datos_del_domicilio', $parsed['records'][0]['layout_key']);
        $this->assertSame([], $parsed['records'][0]['warnings']);
        $this->assertSame('20', $parsed['records'][0]['normalized_data']['variant']['selector_value']);
        $this->assertStringContainsString('territorio', (string) $parsed['records'][0]['normalized_data']['variant']['heading']);
    }
}
