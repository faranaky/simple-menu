<?php
namespace Fary\SimpleMenu\Block;

use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Tree\Node;

class Megamenu extends \Magento\Catalog\Plugin\Block\Topmenu
{
    protected $collectionFactory;

    protected $storeManager;

    protected $layerResolver;

    public function __construct(\Magento\Catalog\Helper\Category $catalogCategory, \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Catalog\Model\Layer\Resolver $layerResolver)
    {
        parent::__construct($catalogCategory, $categoryCollectionFactory, $storeManager, $layerResolver);
        $this->collectionFactory = $categoryCollectionFactory;
        $this->storeManager      = $storeManager;
        $this->layerResolver     = $layerResolver;
    }

    /**
     * Convert category to array
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param \Magento\Catalog\Model\Category $currentCategory
     * @return array
     */
    private function getCategoryAsArray($category, $currentCategory)
    {
        return [
            'name'                 => $category->getName(),
            'navigation_type'      => $category->getData('navigation_type'),
            'is_mega_menu'         => $category->getData('is_mega_menu'),
            'static_link'          => $category->getData('static_link'),
            'cms_page'             => $category->getData('cms_page'),
            'mega_menu_attributes' => $category->getData('mega_menu_attributes'),
            'blank_target'         => $category->getData('blank_target'),
            'promo_block'          => $category->getData('promo_block'),
            'id'                   => 'category-node-' . $category->getId(),
            'url'                  => $this->catalogCategory->getCategoryUrl($category),
            'has_active'           => in_array( (string) $category->getId(), explode('/', $currentCategory->getPath()), true),
            'is_active'            => $category->getId() == $currentCategory->getId()
        ];
    }

    /**
     * Build category tree for menu block.
     *
     * @param \Magento\Theme\Block\Html\Topmenu $subject
     * @param string $outermostClass
     * @param string $childrenWrapClass
     * @param int $limit
     * @return void
     * @SuppressWarnings("PMD.UnusedFormalParameter")
     */
    public function beforeGetHtml(
        \Magento\Theme\Block\Html\Topmenu $subject,
        $outermostClass = '',
        $childrenWrapClass = '',
        $limit = 0
    ) {
        $rootId = $this->storeManager->getStore()->getRootCategoryId();
        $storeId = $this->storeManager->getStore()->getId();
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->getCategoryTree($storeId, $rootId);
        $currentCategory = $this->getCurrentCategory();
        $mapping = [$rootId => $subject->getMenu()];  // use nodes stack to avoid recursion
        foreach ($collection as $category) {
            if (!isset($mapping[$category->getParentId()])) {
                continue;
            }
            /** @var Node $parentCategoryNode */
            $parentCategoryNode = $mapping[$category->getParentId()];

            $categoryNode = new Node(
                $this->getCategoryAsArray($category, $currentCategory),
                'id',
                $parentCategoryNode->getTree(),
                $parentCategoryNode
            );
            $parentCategoryNode->addChild($categoryNode);

            $mapping[$category->getId()] = $categoryNode; //add node in stack
        }
    }

    /**
     * Get current Category from catalog layer
     *
     * @return \Magento\Catalog\Model\Category
     */
    private function getCurrentCategory()
    {
        $catalogLayer = $this->layerResolver->get();

        if (!$catalogLayer) {
            return null;
        }

        return $catalogLayer->getCurrentCategory();
    }

    /**
     * Get Category Tree
     *
     * @param int $storeId
     * @param int $rootId
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCategoryTree($storeId, $rootId)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('is_mega_menu');
        $collection->addAttributeToSelect('navigation_type');
        $collection->addAttributeToSelect('static_link');
        $collection->addAttributeToSelect('mega_menu_attributes');
        $collection->addAttributeToSelect('cms_page');
        $collection->addAttributeToSelect('blank_target');
        $collection->addAttributeToSelect('promo_block');
        $collection->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']); //load only from store root
        $collection->addAttributeToFilter('include_in_menu', 1);
        $collection->addIsActiveFilter();
        $collection->addUrlRewriteToResult();
        $collection->addOrder('level', Collection::SORT_ORDER_ASC);
        $collection->addOrder('position', Collection::SORT_ORDER_ASC);
        $collection->addOrder('parent_id', Collection::SORT_ORDER_ASC);
        $collection->addOrder('entity_id', Collection::SORT_ORDER_ASC);

        return $collection;
    }

}