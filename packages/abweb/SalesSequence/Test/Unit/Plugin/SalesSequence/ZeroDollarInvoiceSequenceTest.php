<?php

declare(strict_types=1);

namespace Abweb\SalesSequence\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Abweb\SalesSequence\Plugin\SalesSequence\ZeroDollarInvoiceSequence;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\SalesSequence\Model\Manager;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\SalesSequence\Model\Sequence;

class ZeroDollarInvoiceSequenceTest extends TestCase
{
    private $sequenceManagerMock;
    private $scopeConfig;
    private $underTest;

    /**
     * @return void
     */
    public function setUp():void
    {
        $this->sequenceManagerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->underTest = new ZeroDollarInvoiceSequence(
            $this->sequenceManagerMock,
            $this->scopeConfig
        );
    }

    /**
     * @return void
     */
    public function testBeforeSave()
    {
        $this->invoiceMock = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->invoiceResourceMock = $this->getMockBuilder(InvoiceResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMock = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sequenceMock = $this->getMockBuilder(Sequence::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->invoiceMock->expects($this->once())->method('getOrder')->willReturn($this->orderMock);
        $this->invoiceMock->expects($this->once())->method('getGrandTotal')->willReturn(0.00);
        $this->orderMock->expects($this->once())->method('getPayment')->willReturn($this->paymentMock);
        $this->paymentMock->expects($this->once())->method('getMethod')->willReturn('free');
        $this->sequenceManagerMock->expects($this->once())->method('getSequence')->willReturn($this->sequenceMock);
        $this->sequenceMock->expects($this->once())->method('getNextValue')->willReturn(10003);
        $this->invoiceMock->expects($this->once())->method('setIncrementId')->with(10003);

        $this->underTest->beforeSave($this->invoiceResourceMock, $this->invoiceMock);
    }

}
