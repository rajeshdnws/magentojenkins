<?php

namespace Abweb\SalesSequence\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class SharedSalesSequenceZeroDollarInvoice implements DataPatchInterface
{

    const SEQUENCE_CONFIG_PATH_PREFIX = 'sales_sequence/';
    const SEQUENCE_ENABLE_PATH_SUFFIX = '/enabled';
    const SEQUENCE_STORE_PATH_SUFFIX = '/shared_store_id';

    const DEFAULT_SCOPE = 'default';
    const BASE_STORE_VIEW_CODE = 'default';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private StoreRepositoryInterface $storeRepository
    ) { }

    /**
     * @return array|string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return SharedSalesSequence|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function apply(): void
    {
        $baseStore = $this->storeRepository->get(self::BASE_STORE_VIEW_CODE);

        if ($baseStore) {
            $baseStoreId = $baseStore->getId();

            $salesSequenceConfigurations = [
                [
                    'path' => self::SEQUENCE_CONFIG_PATH_PREFIX . 'zero_dollar_invoice'. self::SEQUENCE_ENABLE_PATH_SUFFIX,
                    'value' => 1,
                    'scope' => self::DEFAULT_SCOPE,
                    'scope_id' => 0
                ],
                [
                    'path' => self::SEQUENCE_CONFIG_PATH_PREFIX . 'zero_dollar_invoice'. self::SEQUENCE_STORE_PATH_SUFFIX,
                    'value' => $baseStoreId,
                    'scope' => self::DEFAULT_SCOPE,
                    'scope_id' => 0
                ]
            ];

            $this->moduleDataSetup->getConnection()->startSetup();

            foreach ($salesSequenceConfigurations as $config) {
                $this->moduleDataSetup->getConnection()->insertOnDuplicate(
                    $this->moduleDataSetup->getTable('core_config_data'),
                    $config
                );
            }

            $this->moduleDataSetup->getConnection()->endSetup();
        }
    }
}
