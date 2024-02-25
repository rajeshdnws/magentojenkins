<?php
declare(strict_types=1);

namespace Abweb\SalesSequence\Setup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMeta;
use Magento\SalesSequence\Model\ResourceModel\Profile as ResourceProfile;
use Magento\Store\Model\ResourceModel\Store;
use Magento\Store\Model\StoreManagerInterface;

class SequenceHelper
{
    const BASE_URL_PATH = 'web/unsecure/base_url';

    public function __construct(
        private readonly ResourceMeta $sequenceMetaResource,
        private readonly ResourceProfile $profileResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Store $storeResource
    ) {
    }

    /**
     * Get combination of sequence meta/profile data for specified entity types
     *
     * @throws LocalizedException
     */
    public function getSequenceMetaData(array $entityTypes): array
    {
        $entityTypes = $this->getEntityTypes($entityTypes);
        $metaConnection = $this->sequenceMetaResource->getConnection();
        $stores = $this->getStoreList();

        $select = $metaConnection->select()->from(
            ['meta_table' => $this->sequenceMetaResource->getMainTable()],
            [$this->sequenceMetaResource->getIdFieldName(), 'store_id', 'sequence_table']
        )->joinLeft(
            ['profile_table' => $this->profileResource->getMainTable()],
            'profile_table.meta_id = meta_table.' . $this->sequenceMetaResource->getIdFieldName(),
            ['profile_id', 'start_value']
        )->joinLeft(
            ['store_table' => $this->storeResource->getMainTable()],
            'meta_table.store_id = store_table.' . $this->storeResource->getIdFieldName(),
            ['code']
        )->where(
            'meta_table.store_id IN(?)', $stores
        )->where(
            'meta_table.entity_type in (?)',
            $entityTypes
        );

        return $metaConnection->fetchAll($select);
    }


    /**
     * @param array $entityEnvIncrementMap
     * @return string|null
     */
    public function getStartValueForCurrentEnv(array $entityEnvIncrementMap): ?string
    {
        $baseUrl = $this->getBaseUrl();

        foreach ($entityEnvIncrementMap as $envKey => $value) {
            if (strpos($baseUrl, $envKey) !== false) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get base url to determine environment a patch is running on
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->scopeConfig->getValue(self::BASE_URL_PATH);
    }

    /**
     * @return array
     */
    protected function getStoreList(): array
    {
        $stores = [];
        $storeList = $this->storeManager->getStores(true);
        foreach ($storeList as $store) {
            $stores[] = $store->getId();
        }
        return $stores;
    }

    /**
     * @throws LocalizedException
     */
    protected function getEntityTypes(array $entityTypes): array
    {
        $metaConnection = $this->sequenceMetaResource->getConnection();

        $metaSelect = $metaConnection->select()
            ->from($this->sequenceMetaResource->getMainTable(), ['entity_type'])
            ->distinct()
            ->where('entity_type in (?)', $entityTypes);

        $results = $metaConnection->fetchCol($metaSelect);

        if (empty($entityTypes)) {
            throw new LocalizedException(__('Entity types not found.'));
        }

        return $results;
    }
}
