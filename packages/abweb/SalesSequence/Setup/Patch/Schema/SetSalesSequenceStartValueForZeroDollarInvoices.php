<?php
declare(strict_types=1);

namespace Abweb\SalesSequence\Setup\Patch\Schema;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMeta;
use Magento\SalesSequence\Model\ResourceModel\Profile as ResourceProfile;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SetSalesSequenceStartValueForZeroDollarInvoices implements SchemaPatchInterface
{
    const ENTITY_TYPE = 'zero_dollar_invoice';


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
        $connection = $this->moduleDataSetup->getConnection()->startSetup();

        $this->updateSequenceStartValues($connection);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @param $connection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function updateSequenceStartValues($connection): void
    {
        // select query to fetch valid entries from tables
        $selectMetaCollection = $connection->select()
            ->from(
                ['meta_table' => $this->sequenceMetaResource->getMainTable()],
                [$this->sequenceMetaResource->getIdFieldName(), 'sequence_table']
            )->joinLeft(
                ['profile_table' => $this->profileResource->getMainTable()],
                'profile_table.meta_id = meta_table.' . $this->sequenceMetaResource->getIdFieldName(),
                ['profile_id', 'start_value']
            )->where('meta_table.entity_type = (?)', self::ENTITY_TYPE);

        $sequenceMetaCollection = $connection->fetchAll($selectMetaCollection);
        foreach ($sequenceMetaCollection as $meta) {
            if (!empty($meta['start_value'])) {
                $this->updateStartValue($meta, $connection);
            }
        }
    }

    /**
     * @param $meta
     * @param $connection
     * @return void
     */
    private function updateStartValue($meta, $connection)
    {
        //alter the auto increment value
        $sql = sprintf('ALTER TABLE %s AUTO_INCREMENT= %s', $meta['sequence_table'], $meta['start_value']);
        $connection->getConnection()->query($sql);
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
            CreateZeroDollarInvoiceSequence::class
        ];
    }
}
