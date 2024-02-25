<?php

declare (strict_types=1);

namespace Abweb\SalesSequence\Plugin\SalesSequence;

use Magento\SalesSequence\Model\Meta as SalesSequenceMeta;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Meta
{

    const SEQUENCE_CONFIG_PATH_PREFIX = 'sales_sequence/';
    const SEQUENCE_ENABLE_PATH_SUFFIX = '/enabled';
    const SEQUENCE_STORE_PATH_SUFFIX = '/shared_store_id';

    const SEQUENCE_TABLE_FIELD = 'sequence_table';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) { }

    /**
     * @param SalesSequenceMeta $subject
     * @param $result
     * @param $field
     * @return mixed|string
     */
    public function afterGetData(SalesSequenceMeta $subject, $result, $field = null)
    {
        if ($field === self::SEQUENCE_TABLE_FIELD) {
            $entityType = $subject->getEntityType();
            if ($this->isSharedSequenceEnabledForEntity($entityType, $subject->getStoreId())) {
                $sharedStoreId = $this->getSharedStoreIdForEntity($entityType, $subject->getStoreId());
                if ($sharedStoreId) {
                    return 'sequence_'.$entityType.'_'.$sharedStoreId;
                }
            }
        }
        return $result;
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function isSharedSequenceEnabledForEntity($entity, $storeId = null)
    {
        return $this->getConfig(self::SEQUENCE_CONFIG_PATH_PREFIX . $entity . self::SEQUENCE_ENABLE_PATH_SUFFIX, $storeId);
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function getSharedStoreIdForEntity($entity, $storeId = null)
    {
        return $this->getConfig(self::SEQUENCE_CONFIG_PATH_PREFIX . $entity . self::SEQUENCE_STORE_PATH_SUFFIX, $storeId);
    }

    /**
     * @param $path
     * @param $storeId
     * @return mixed
     */
    private function getConfig($path, $storeId = null)
    {
        if($storeId) {
            return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return  $this->scopeConfig->getValue($path);
        }
    }
}
