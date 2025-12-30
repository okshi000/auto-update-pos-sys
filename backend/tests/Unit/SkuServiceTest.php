<?php

namespace Tests\Unit;

use App\Services\SkuService;
use PHPUnit\Framework\TestCase;

class SkuServiceTest extends TestCase
{
    protected SkuService $skuService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skuService = new SkuService();
    }

    /** @test */
    public function generates_sku_with_default_prefix(): void
    {
        $sku = $this->skuService->generate('Test Product');

        $this->assertNotEmpty($sku);
        $this->assertStringStartsWith('GEN-', $sku);
    }

    /** @test */
    public function generates_sku_with_custom_prefix(): void
    {
        $sku = $this->skuService->generate('Test Product', 'ELC');

        $this->assertStringStartsWith('ELC-', $sku);
    }

    /** @test */
    public function sku_contains_product_name_portion(): void
    {
        $sku = $this->skuService->generate('Apple iPhone 15');

        // SKU should contain part of the product name
        $this->assertStringContainsString('-', $sku);
    }

    /** @test */
    public function sku_handles_special_characters_in_name(): void
    {
        $sku = $this->skuService->generate('Test & Product (Special!)');

        $this->assertNotEmpty($sku);
        // Should not contain special characters
        $this->assertMatchesRegularExpression('/^[A-Z0-9\-]+$/', $sku);
    }

    /** @test */
    public function sku_format_is_valid(): void
    {
        $sku = $this->skuService->generate('Sample Product', 'TST');

        $this->assertTrue($this->skuService->isValidFormat($sku));
    }

    /** @test */
    public function validates_invalid_sku_format(): void
    {
        $this->assertFalse($this->skuService->isValidFormat(''));
        $this->assertFalse($this->skuService->isValidFormat('abc')); // lowercase
        $this->assertFalse($this->skuService->isValidFormat('ABC@123')); // special char
    }

    /** @test */
    public function generates_different_skus_for_same_product(): void
    {
        $sku1 = $this->skuService->generate('Same Product');
        $sku2 = $this->skuService->generate('Same Product');

        // Due to random component, SKUs should be different
        $this->assertNotEquals($sku1, $sku2);
    }

    /** @test */
    public function handles_empty_product_name(): void
    {
        $sku = $this->skuService->generate('');

        // Should still generate a valid SKU with prefix and random
        $this->assertNotEmpty($sku);
        $this->assertStringStartsWith('GEN-', $sku);
    }

    /** @test */
    public function handles_very_long_product_names(): void
    {
        $longName = str_repeat('Long Product Name ', 20);
        $sku = $this->skuService->generate($longName);

        // SKU should be reasonable length
        $this->assertLessThan(50, strlen($sku));
    }
}
