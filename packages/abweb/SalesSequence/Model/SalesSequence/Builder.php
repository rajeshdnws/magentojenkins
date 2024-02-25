<?php

declare (strict_types=1);

namespace Abweb\SalesSequence\Model\SalesSequence;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\Framework\DB\Ddl\Sequence as DdlSequence;
use Magento\SalesSequence\Model\Builder as SalesSequenceBuilder;
use Magento\SalesSequence\Model\MetaFactory;
use Magento\SalesSequence\Model\ProfileFactory;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMetadata;
use Psr\Log\LoggerInterface as Logger;
use Magento\Store\Model\ScopeInterface;

class Builder extends SalesSequenceBuilder
{

    const SEQUENCE_CONFIG_PATH_PREFIX = 'sales_sequence/';
    const SEQUENCE_ENABLE_PATH_SUFFIX = '/enabled';
    const SEQUENCE_STORE_PATH_SUFFIX = '/shared_store_id';

    public function __construct(
        ResourceMetadata $resourceMetadata,
        MetaFactory $metaFactory,
        ProfileFactory $profileFactory,
        AppResource $appResource,
        DdlSequence $ddlSequence,
        Logger $logger,
        private ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($resourceMetadata, $metaFactory, $profileFactory, $appResource, $ddlSequence, $logger);
    }

    /**
     * Returns sequence table name
     *
     * @return string
     */
    protected function getSequenceName()
    {
        $entityType = $this->data['entity_type'];
        if ($this->isSharedSequenceEnabledForEntity($entityType)) {
            $sharedStoreId = $this->getSharedStoreIdForEntity($entityType);
            if ($sharedStoreId) {
                return $this->appResource->getTableName(
                    sprintf(
                        'sequence_%s_%s',
                        $entityType,
                        $sharedStoreId
                    )
                );
            }
        }
        return $this->appResource->getTableName(
            sprintf(
                'sequence_%s_%s',
                $entityType,
                $this->data['store_id']
            )
        );
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function isSharedSequenceEnabledForEntity($entity)
    {
        return $this->getConfig(self::SEQUENCE_CONFIG_PATH_PREFIX . $entity . self::SEQUENCE_ENABLE_PATH_SUFFIX);
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function getSharedStoreIdForEntity($entity)
    {
        return $this->getConfig(self::SEQUENCE_CONFIG_PATH_PREFIX . $entity . self::SEQUENCE_STORE_PATH_SUFFIX);
    }

    /**
     * @param $path
     * @return mixed
     */
    private function getConfig($path)
    {
        if(isset($this->data["store_id"]) && $this->data["store_id"]) {
            return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $this->data["store_id"]);
        } else {
            return  $this->scopeConfig->getValue($path);
        }
    }
}
