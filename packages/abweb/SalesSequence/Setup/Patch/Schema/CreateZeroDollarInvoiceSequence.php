<?php

namespace Abweb\SalesSequence\Setup\Patch\Schema;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\SalesSequence\Model\Config;
use Magento\SalesSequence\Model\Builder;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMetadata;
use Magento\Store\Api\StoreRepositoryInterface;

class CreateZeroDollarInvoiceSequence implements SchemaPatchInterface
{
    const ENTITY_TYPE = 'zero_dollar_invoice';
    const BASE_STORE_VIEW_CODE = 'default';
    const ZERO_DOLLAR_INVOICE_PREFIX = 'KKFI';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var Config
     */
    protected $sequenceConfig;
    /**
     * @var Builder
     */
    protected $sequenceBuilder;
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $connection;
    /**
     * @var resourceMetadata
     */
    protected $resourceMetadata;

    /**
     * @var StoreRepositoryInterface
     */
    protected $storeRepository;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Config $sequenceConfig
     * @param Builder $builder
     * @param ResourceMetadata $resourceMetadata
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        Config $sequenceConfig,
        Builder $builder,
        ResourceMetadata $resourceMetadata,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->sequenceConfig = $sequenceConfig;
        $this->sequenceBuilder = $builder;
        $this->resourceMetadata = $resourceMetadata;
        $this->connection = $this->moduleDataSetup->getConnection();
        $this->storeRepository =  $storeRepository;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $storeId = $this->getStoreId();
        $metadata = $this->resourceMetadata->loadByEntityTypeAndStore(self::ENTITY_TYPE, $storeId);
        if (!$metadata->getId() && $storeId) {
            $this->createSequence(self::ENTITY_TYPE, $storeId, $this->getStartValue());
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return int|mixed
     */
    protected function getStartValue()
    {
        $salesInvoiceTable = $this->connection->getTableName('sales_invoice');
        $query = "SELECT increment_id FROM $salesInvoiceTable WHERE increment_id LIKE 'KK%'";
        $result = $this->connection->fetchAll($query);
        $sequenceValues = [];
        if ($result) {
            foreach ($result as $item) {
                $sequenceValues[] = preg_replace("/[^0-9]/", "", $item['increment_id']);
            }
            return max($sequenceValues) + 1;
        } else {
            return 1;
        }
    }

    /**
     * @return int|null
     */
    protected function getStoreId()
    {
        try {
            $baseStore = $this->storeRepository->get(self::BASE_STORE_VIEW_CODE);
            return $baseStore->getId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create the sequence entries meta + sequence table
     *
     * @param $entityType
     * @param $storeId
     * @param $startValue
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function createSequence($entityType, $storeId, $startValue)
    {
        $this->sequenceBuilder->setPrefix(self::ZERO_DOLLAR_INVOICE_PREFIX)
            ->setSuffix($this->sequenceConfig->get('suffix'))
            ->setStartValue($startValue)
            ->setStoreId($storeId)
            ->setStep($this->sequenceConfig->get('step'))
            ->setWarningValue($this->sequenceConfig->get('warningValue'))
            ->setMaxValue($this->sequenceConfig->get('maxValue'))
            ->setEntityType($entityType)
            ->create();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
