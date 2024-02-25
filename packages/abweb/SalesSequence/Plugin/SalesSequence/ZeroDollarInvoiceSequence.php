<?php
namespace Abweb\SalesSequence\Plugin\SalesSequence;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Magento\SalesSequence\Model\Manager;
use Magento\Framework\Exception\LocalizedException;


class ZeroDollarInvoiceSequence
{
    const ZERO_TOTAL = 0.00;
    const ZERO_DOLLAR_ENTITY_TYPE = 'zero_dollar_invoice';
    const IS_ZZ_INVOICE_PREFIX = 'sales_sequence/zero_dollar_invoice/zz_invoice';

    /**
     * @var Manager
     */
    private Manager $sequenceManager;

    /**
     * @param Manager $sequenceManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Manager $sequenceManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->sequenceManager = $sequenceManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param InvoiceResource $subject
     * @param Invoice $invoice
     * @return void
     * @throws LocalizedException
     */
    public function beforeSave(InvoiceResource $subject, Invoice $invoice) {
        $order = $invoice->getOrder();
        $isZeroDollarInvoice = ($invoice->getGrandTotal() == self::ZERO_TOTAL)
            && ($order->getPayment()->getMethod() == \Magento\Payment\Model\Method\Free::PAYMENT_METHOD_FREE_CODE);
        if ($isZeroDollarInvoice) {
            $zzInvoicePrefix = $this->getZzInvoiceConfig($invoice->getStoreId());
            if (!empty($zzInvoicePrefix)) {
                $incrementId = $this->sequenceManager->getSequence(self::ZERO_DOLLAR_ENTITY_TYPE, $this->getSharedStoreIdForZZInvoice())->getNextValue();
                $invoice->setIncrementId($incrementId);
            }
        }
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function getSharedStoreIdForZZInvoice()
    {
        return $this->getConfig('sales_sequence/zero_dollar_invoice/shared_store_id');
    }

    /**
     * @param $path
     * @param $scopeValue
     * @return mixed
     */
    private function getConfig($path)
    {
        return $this->scopeConfig->getValue($path);
    }

    /**
     * Get zero total invoice prefix with ZZ yes or no
     *
     * @param int $storeId
     * @return mixed
     */
    private function getZzInvoiceConfig($storeId)
    {
        return $this->scopeConfig->getValue(self::IS_ZZ_INVOICE_PREFIX, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
