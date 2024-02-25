<?php

declare(strict_types=1);

namespace Abweb\SalesSequence\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\SalesSequence\Model\ResourceModel\Profile as ResourceProfile;
use Magento\SalesSequence\Model\Profile as ModelProfile;
use Magento\SalesSequence\Model\ResourceModel\Meta as ResourceMeta;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class SetSalesSequencePrefixes implements DataPatchInterface
{
    const COUNTRY_CODE_PATH = 'general/country/default';
    const ADMIN_STORE_ID = 0;
    const ENTITY_INDICATOR_MAP = [
        'invoice' => 'I',
        'creditmemo' => 'C'
    ];
    const ENTITY_PREFIX_OVERRIDE_MAP = [
        'rma_item' => ''
    ];

    protected StoreManagerInterface $storeManager;
    protected ModuleDataSetupInterface $moduleDataSetup;
    protected ResourceProfile $profileResource;
    protected ResourceMeta $resourceMeta;
    protected ScopeConfigInterface $scopeConfig;
    protected ModelProfile $modelProfile;
    protected LoggerInterface $logger;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        StoreManagerInterface $storeManager,
        ResourceProfile $profileResource,
        ModelProfile $modelProfile,
        ResourceMeta $resourceMeta,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->profileResource = $profileResource;
        $this->modelProfile = $modelProfile;
        $this->resourceMeta = $resourceMeta;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->logger->info('SetSalesSequencePrefixesV2 patch execution starts.');
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $stores = $this->getStoreList();
            $metaConnection = $this->resourceMeta->getConnection();

            $selectMetaCollection = $this->resourceMeta->getConnection()
                ->select()
                ->from(
                    ['meta_table' => $this->resourceMeta->getMainTable()],
                    [$this->resourceMeta->getIdFieldName(), 'store_id', 'entity_type']
                )
                ->joinLeft(
                    ['profile_table' => $this->profileResource->getMainTable()],
                    'profile_table.meta_id = meta_table.' . $this->resourceMeta->getIdFieldName(),
                )
                ->where(
                    'store_id IN(?)',
                    $stores
                );

            $metaCollectionWithProfileData = $metaConnection->fetchAll($selectMetaCollection);

            foreach ($metaCollectionWithProfileData as $metaWithProfile) {
                $this->logger->info('Set sales sequence prefix for profile id=' . $metaWithProfile['profile_id']);
                $countryCode = $this->getCountryCodeOfStore((int)$metaWithProfile['store_id']);
                $this->logger->info('CountryCode for store =' . $countryCode);

                $prefixOverride = self::ENTITY_PREFIX_OVERRIDE_MAP[$metaWithProfile['entity_type']] ?? false;
                $entityIndicator = self::ENTITY_INDICATOR_MAP[$metaWithProfile['entity_type']] ?? '';
                $prefix = $countryCode . $entityIndicator;

                if ($prefixOverride !== false) {
                    $prefix = $prefixOverride;
                }

                $data = ['prefix' => $prefix];
                $where = ['profile_id = ?' => (int)$metaWithProfile['profile_id']];
                $this->moduleDataSetup->getConnection()->update($this->profileResource->getMainTable(), $data, $where);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error occurred while setting sales entity prefixes.' . $e->getMessage());
        }

        $this->logger->info('SetSalesSequencePrefixesV patch execution ended.');

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return array
     */

    /**
     * @return array
     */
    public function getStoreList(): array
    {
        $stores = [];
        $storeList = $this->storeManager->getStores(true);

        foreach ($storeList as $store) {
            $stores[] = $store->getId();
        }

        return $stores;
    }

    /**
     * Get default country configuration for store
     *
     * @param int $storeId
     * @return string
     */
    protected function getCountryCodeOfStore(int $storeId): string
    {
        $countryCode = '';

        try {
            // for admin store assign the default store country id
            $store = ($storeId == self::ADMIN_STORE_ID) ?
                $this->storeManager->getDefaultStoreView() : $this->storeManager->getStore($storeId);

            if ($store->getId()) {
                $websiteId = (int)$store->getWebsiteId();
                $countryCode = $this->getCountryByWebsite($websiteId);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error($e->getMessage());
        }

        return $countryCode;
    }

    /**
     * Get default country configuration for website
     *
     * @param int $websiteId
     * @return string
     */
    protected function getCountryByWebsite(int $websiteId): string
    {
        return $this->scopeConfig->getValue(
            self::COUNTRY_CODE_PATH,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
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
        return [];
    }
}
