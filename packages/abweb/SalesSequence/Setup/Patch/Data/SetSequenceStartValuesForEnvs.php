<?php
declare(strict_types=1);

namespace Abweb\SalesSequence\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMeta;
use Magento\SalesSequence\Model\ResourceModel\Profile as ResourceProfile;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SetSequenceStartValuesForEnvs implements DataPatchInterface
{
    const DEV_ENV_URL_KEYWORDS = 'dev.';
    const DEV2_ENV_URL_KEYWORDS = 'dev2.';
    const QA_ENV_URL_KEYWORDS = 'qa.';
    const QA2_ENV_URL_KEYWORDS = 'qa2.';
    const STAGE_ENV_URL_KEYWORDS = 'staging.';

    const SALES_ID_START_VALUE_ARRAY = [
        self::DEV_ENV_URL_KEYWORDS => '900002000',
        self::DEV2_ENV_URL_KEYWORDS => '910002000',
        self::QA_ENV_URL_KEYWORDS => '920002000',
        self::QA2_ENV_URL_KEYWORDS => '930002000',
        self::STAGE_ENV_URL_KEYWORDS => '940002000',
    ];

    const RMA_ID_START_VALUE_ARRAY = [
        self::DEV_ENV_URL_KEYWORDS => '290002000',
        self::DEV2_ENV_URL_KEYWORDS => '291002000',
        self::QA_ENV_URL_KEYWORDS => '292002000',
        self::QA2_ENV_URL_KEYWORDS => '293002000',
        self::STAGE_ENV_URL_KEYWORDS => '294002000',
    ];

    const BASE_URL_PATH = 'web/unsecure/base_url';

    private ModuleDataSetupInterface $moduleDataSetup;
    private StoreManagerInterface $storeManager;
    private ResourceProfile $profileResource;
    private ResourceMeta $sequenceMetaResource;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ResourceMeta $sequenceMetaResource,
        ResourceProfile $profileResource,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->profileResource = $profileResource;
        $this->storeManager = $storeManager;
        $this->sequenceMetaResource = $sequenceMetaResource;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $this->updateStartValuesAllButRma();
        $this->updateStartValuesForRma();

        $connection->endSetup();
    }

    protected function updateStartValuesAllButRma(): void
    {
        $metaConnection = $this->sequenceMetaResource->getConnection();

        $metaSelect = $metaConnection->select()
            ->from($this->sequenceMetaResource->getMainTable(), ['entity_type'])
            ->distinct()
            ->where('entity_type not in (?)', ['rma_item']);

        $entityTypes = $metaConnection->fetchCol($metaSelect);
        $startValue = $this->getStartValueForCurrentEnv(self::SALES_ID_START_VALUE_ARRAY);

        $this->updateSequenceStartValues($startValue, $entityTypes);
    }

    protected function updateStartValuesForRma(): void
    {
        $metaConnection = $this->sequenceMetaResource->getConnection();

        $metaSelect = $metaConnection->select()
            ->from($this->sequenceMetaResource->getMainTable(), ['entity_type'])
            ->distinct()
            ->where('entity_type in (?)', ['rma_item']);

        $entityTypes = $metaConnection->fetchCol($metaSelect);
        $startValue = $this->getStartValueForCurrentEnv(self::RMA_ID_START_VALUE_ARRAY);

        $this->updateSequenceStartValues($startValue, $entityTypes);
    }

    protected function updateSequenceStartValues(string $startValue, array $entityTypes): bool
    {
        if (empty($startValue) || empty($entityTypes)) {
            $baseUrl = $this->scopeConfig->getValue(self::BASE_URL_PATH);
            $this->logger->info('SetSequenceStartValuesForEnvs patch skipped for base url: ' . $baseUrl);
            return false;
        }

        // get the valid stores array
        $stores = $this->getStoreList();

        // prepare the resource connections
        $metaConnection = $this->sequenceMetaResource->getConnection();
        $profileConnection = $this->profileResource->getConnection();

        // select query to fetch valid entries from tables
        $selectMetaCollection = $metaConnection->select()
            ->from(
                ['meta_table' => $this->sequenceMetaResource->getMainTable()],
                [$this->sequenceMetaResource->getIdFieldName(), 'store_id', 'sequence_table']
            )->joinLeft(
                ['profile_table' => $this->profileResource->getMainTable()],
            'profile_table.meta_id = meta_table.' . $this->sequenceMetaResource->getIdFieldName(),
                ['profile_id']
            )->where('store_id IN(?)', $stores)
            ->where('meta_table.entity_type in (?)', $entityTypes);

        $sequenceMetaCollection = $metaConnection->fetchAll($selectMetaCollection);
        foreach ($sequenceMetaCollection as $meta) {
            $data = ['start_value' => $startValue];
            if (!empty($data['start_value'])) {
                $this->updateTheStartId($meta, $data, $profileConnection, $this->profileResource->getMainTable());
            }
        }

        return true;
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
     * @param $meta
     * @param $data
     * @param $connection
     * @param $profileTable
     */
    private function updateTheStartId($meta, $data, $connection, $profileTable): void
    {
        $where = ['profile_id = ?' => (int)$meta['profile_id']];
        $connection->update($profileTable, $data, $where);

        //alter the auto increment value
        $sql = sprintf('ALTER TABLE %s AUTO_INCREMENT= %s', $meta['sequence_table'], $data['start_value']);
        $connection->getConnection()->query($sql);
    }

    /**
     * @param array $entityEnvIncrementMap
     * @return string
     */
    protected function getStartValueForCurrentEnv(array $entityEnvIncrementMap): string
    {
        $baseUrl = $this->scopeConfig->getValue(self::BASE_URL_PATH);
        $startValue = '';
        foreach ($entityEnvIncrementMap as $envKey => $value) {
            if (strpos($baseUrl, $envKey) !== false) {
                return $value;
            }
        }
        return $startValue;
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            SetSalesSequencePrefixes::class
        ];
    }
}
