<?php

namespace Tests\Unit;

use App\Services\BarcodeService;
use PHPUnit\Framework\TestCase;

class BarcodeServiceTest extends TestCase
{
    protected BarcodeService $barcodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->barcodeService = new BarcodeService();
    }

    /** @test */
    public function validates_correct_ean13_barcode(): void
    {
        // Valid EAN-13 barcodes
        $this->assertTrue($this->barcodeService->validate('5449000000996')); // Coca-Cola
        $this->assertTrue($this->barcodeService->validate('4006381333931'));
        $this->assertTrue($this->barcodeService->validate('0012000001239')); // With leading zero (UPC-A as EAN-13)
    }

    /** @test */
    public function rejects_invalid_ean13_checksum(): void
    {
        $this->assertFalse($this->barcodeService->validate('5449000000997')); // Wrong check digit
        $this->assertFalse($this->barcodeService->validate('5449000000990'));
    }

    /** @test */
    public function validates_correct_ean8_barcode(): void
    {
        $this->assertTrue($this->barcodeService->validate('96385074'));
        $this->assertTrue($this->barcodeService->validate('50184385'));
    }

    /** @test */
    public function rejects_invalid_ean8_checksum(): void
    {
        $this->assertFalse($this->barcodeService->validate('96385071')); // Wrong check digit
    }

    /** @test */
    public function validates_correct_upca_barcode(): void
    {
        $this->assertTrue($this->barcodeService->validate('012345678905'));
        $this->assertTrue($this->barcodeService->validate('042100005264'));
    }

    /** @test */
    public function rejects_invalid_upca_checksum(): void
    {
        $this->assertFalse($this->barcodeService->validate('012345678900')); // Wrong check digit
    }

    /** @test */
    public function rejects_invalid_length(): void
    {
        $this->assertFalse($this->barcodeService->validate('123456')); // Too short
        $this->assertFalse($this->barcodeService->validate('12345678901234')); // Too long
        $this->assertFalse($this->barcodeService->validate('1234567890')); // Invalid length
    }

    /** @test */
    public function rejects_non_numeric_barcode(): void
    {
        $this->assertFalse($this->barcodeService->validate('544900000099A'));
        $this->assertFalse($this->barcodeService->validate('5449000-00996'));
        $this->assertFalse($this->barcodeService->validate('ABCDEFGHIJKLM'));
    }

    /** @test */
    public function rejects_empty_barcode(): void
    {
        $this->assertFalse($this->barcodeService->validate(''));
        $this->assertFalse($this->barcodeService->validate(null));
    }

    /** @test */
    public function correctly_identifies_barcode_type(): void
    {
        $this->assertEquals('EAN-13', $this->barcodeService->getType('5449000000996'));
        $this->assertEquals('EAN-8', $this->barcodeService->getType('96385074'));
        $this->assertEquals('UPC-A', $this->barcodeService->getType('012345678905'));
    }

    /** @test */
    public function generates_valid_ean13(): void
    {
        $barcode = $this->barcodeService->generateEan13();

        $this->assertEquals(13, strlen($barcode));
        $this->assertTrue($this->barcodeService->validate($barcode));
    }

    /** @test */
    public function generates_ean13_with_prefix(): void
    {
        $barcode = $this->barcodeService->generateEan13('599');

        $this->assertStringStartsWith('599', $barcode);
        $this->assertTrue($this->barcodeService->validate($barcode));
    }

    /** @test */
    public function generated_barcodes_are_unique(): void
    {
        $barcodes = [];
        for ($i = 0; $i < 100; $i++) {
            $barcodes[] = $this->barcodeService->generateEan13();
        }

        $uniqueBarcodes = array_unique($barcodes);
        $this->assertCount(100, $uniqueBarcodes);
    }

    /** @test */
    public function handles_whitespace_in_barcode(): void
    {
        // Barcodes with whitespace should fail
        $this->assertFalse($this->barcodeService->validate(' 5449000000996'));
        $this->assertFalse($this->barcodeService->validate('5449000000996 '));
        $this->assertFalse($this->barcodeService->validate('5449 000000996'));
    }
}
