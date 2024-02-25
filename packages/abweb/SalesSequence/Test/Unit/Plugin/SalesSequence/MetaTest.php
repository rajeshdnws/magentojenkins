<?php

declare(strict_types=1);

namespace Abweb\SalesSequence\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Abweb\SalesSequence\Plugin\SalesSequence\Meta;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\SalesSequence\Model\Meta as SalesSequenceMeta;

class MetaTest extends TestCase
{
    private $scopeConfig;
    private $underTest;

    const TESTING_ENTITY_TYPE_SHARED = 'order';
    const TESTING_ENTITY_TYPE_UNIQUE = 'invoice';
    const VALID_RESULT = 'valid';

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->scopeConfig->method('getValue')->willReturnMap([
            [Meta::SEQUENCE_CONFIG_PATH_PREFIX . self::TESTING_ENTITY_TYPE_SHARED . Meta::SEQUENCE_ENABLE_PATH_SUFFIX, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, '1'],
            [Meta::SEQUENCE_CONFIG_PATH_PREFIX . self::TESTING_ENTITY_TYPE_SHARED . Meta::SEQUENCE_STORE_PATH_SUFFIX, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, '2'],
            [Meta::SEQUENCE_CONFIG_PATH_PREFIX . self::TESTING_ENTITY_TYPE_UNIQUE . Meta::SEQUENCE_ENABLE_PATH_SUFFIX, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, '0'],
            [Meta::SEQUENCE_CONFIG_PATH_PREFIX . self::TESTING_ENTITY_TYPE_UNIQUE . Meta::SEQUENCE_STORE_PATH_SUFFIX, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, ''],
        ]);

        $this->underTest = new Meta($this->scopeConfig);
    }

    private function setEntityType(SalesSequenceMeta $subject, string $value): void
    {
        $subject->expects($this->any())->method('__call')->with('getEntityType', [])->willReturn($value);
    }

    /**
     * @return void
     */
    public function testUnrelated(): void
    {
        $subject = $this->getMockBuilder(SalesSequenceMeta::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setEntityType($subject, self::TESTING_ENTITY_TYPE_SHARED);

        $result = $this->underTest->afterGetData($subject, self::VALID_RESULT, 'unrelated');
        $this->assertEquals(self::VALID_RESULT, $result);
    }

    /**
     * @return void
     */
    public function testShared(): void
    {
        $subject = $this->getMockBuilder(SalesSequenceMeta::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setEntityType($subject, self::TESTING_ENTITY_TYPE_SHARED);

        $result = $this->underTest->afterGetData($subject, 'WRONG', Meta::SEQUENCE_TABLE_FIELD);
        $this->assertEquals('sequence_order_2', $result);
    }

    /**
     * @return void
     */
    public function testUnique(): void
    {
        $subject = $this->getMockBuilder(SalesSequenceMeta::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->setEntityType($subject, self::TESTING_ENTITY_TYPE_UNIQUE);

        $result = $this->underTest->afterGetData($subject, self::VALID_RESULT, Meta::SEQUENCE_TABLE_FIELD);
        $this->assertEquals(self::VALID_RESULT, $result);
    }
}
