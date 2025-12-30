<?php

namespace Tests\Unit;

use App\Helpers\MoneyHelper;
use Tests\TestCase;

class MoneyHelperTest extends TestCase
{
    /** @test */
    public function it_formats_money_with_three_decimal_places(): void
    {
        $formatted = MoneyHelper::format(1234.5, includeSymbol: false, locale: 'en');
        
        $this->assertEquals('1,234.500', $formatted);
    }

    /** @test */
    public function it_formats_money_with_lyd_symbol_in_english(): void
    {
        $formatted = MoneyHelper::format(1234.5, includeSymbol: true, locale: 'en');
        
        $this->assertEquals('LYD 1,234.500', $formatted);
    }

    /** @test */
    public function it_formats_money_with_arabic_symbol(): void
    {
        $formatted = MoneyHelper::format(1234.5, includeSymbol: true, locale: 'ar');
        
        $this->assertEquals('1,234.500 د.ل', $formatted);
    }

    /** @test */
    public function it_converts_to_float_with_precision(): void
    {
        $result = MoneyHelper::toFloat('1234.56789');
        
        $this->assertEquals(1234.568, $result);
    }

    /** @test */
    public function it_parses_formatted_string_to_float(): void
    {
        $result = MoneyHelper::parse('LYD 1,234.500');
        
        $this->assertEquals(1234.5, $result);
    }

    /** @test */
    public function it_calculates_percentage(): void
    {
        $result = MoneyHelper::percentage(100, 15);
        
        $this->assertEquals(15.0, $result);
    }

    /** @test */
    public function it_adds_tax_correctly(): void
    {
        $result = MoneyHelper::addTax(100, 15);
        
        $this->assertEquals([
            'amount' => 100.0,
            'tax' => 15.0,
            'total' => 115.0,
        ], $result);
    }

    /** @test */
    public function it_extracts_tax_from_inclusive_amount(): void
    {
        $result = MoneyHelper::extractTax(115, 15);
        
        $this->assertEquals(100.0, $result['amount']);
        $this->assertEquals(15.0, $result['tax']);
        $this->assertEquals(115.0, $result['total']);
    }

    /** @test */
    public function it_returns_currency_info(): void
    {
        $info = MoneyHelper::getCurrencyInfo();
        
        $this->assertEquals('LYD', $info['code']);
        $this->assertEquals(3, $info['decimal_places']);
        $this->assertArrayHasKey('symbol', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('position', $info);
    }

    /** @test */
    public function it_handles_zero_amounts(): void
    {
        $formatted = MoneyHelper::format(0, includeSymbol: false, locale: 'en');
        
        $this->assertEquals('0.000', $formatted);
    }

    /** @test */
    public function it_handles_negative_amounts(): void
    {
        $formatted = MoneyHelper::format(-1234.5, includeSymbol: false, locale: 'en');
        
        $this->assertEquals('-1,234.500', $formatted);
    }

    /** @test */
    public function currency_code_is_lyd(): void
    {
        $this->assertEquals('LYD', MoneyHelper::CURRENCY_CODE);
    }

    /** @test */
    public function precision_is_three(): void
    {
        $this->assertEquals(3, MoneyHelper::PRECISION);
    }
}
