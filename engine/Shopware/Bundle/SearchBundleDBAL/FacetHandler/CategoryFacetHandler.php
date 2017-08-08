<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\SearchBundleDBAL\FacetHandler;

use Shopware\Search\Condition\CategoryCondition;
use Shopware\Search\Criteria;
use Shopware\Bundle\SearchBundle\Facet\CategoryFacet;
use Shopware\Search\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\CategoryTreeFacetResultBuilder;
use Shopware\Search\FacetResultInterface;
use Shopware\Bundle\SearchBundleDBAL\PartialFacetHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Category\CategoryDepthService;
use Shopware\Bundle\StoreFrontBundle\Category\CategoryServiceInterface;
use Shopware\Context\Struct\ShopContext;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CategoryFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @var \Shopware\Bundle\StoreFrontBundle\Category\CategoryServiceInterface
     */
    private $categoryService;

    /**
     * @var QueryBuilderFactoryInterface
     */
    private $queryBuilderFactory;

    /**
     * @var \Shopware_Components_Config
     */
    private $config;

    /**
     * @var CategoryDepthService
     */
    private $categoryDepthService;

    /**
     * @var CategoryTreeFacetResultBuilder
     */
    private $categoryTreeFacetResultBuilder;

    /**
     * @param \Shopware\Bundle\StoreFrontBundle\Category\CategoryServiceInterface $categoryService
     * @param QueryBuilderFactoryInterface                                        $queryBuilderFactory
     * @param \Shopware_Components_Config                                         $config
     * @param CategoryDepthService                                                $categoryDepthService
     * @param CategoryTreeFacetResultBuilder                                      $categoryTreeFacetResultBuilder
     */
    public function __construct(
        CategoryServiceInterface $categoryService,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        \Shopware_Components_Config $config,
        CategoryDepthService $categoryDepthService,
        CategoryTreeFacetResultBuilder $categoryTreeFacetResultBuilder
    ) {
        $this->categoryService = $categoryService;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->config = $config;
        $this->categoryDepthService = $categoryDepthService;
        $this->categoryTreeFacetResultBuilder = $categoryTreeFacetResultBuilder;
    }

    /**
     * @param FacetInterface|CategoryFacet $facet
     * @param Criteria                     $reverted
     * @param \Shopware\Search\Criteria                     $criteria
     * @param ShopContext         $context
     *
     * @return \Shopware\Search\FacetResultInterface
     */
    public function generatePartialFacet(
        FacetInterface $facet,
        Criteria $reverted,
        Criteria $criteria,
        ShopContext $context
    ) {
        $ids = $this->fetchCategoriesOfProducts($reverted, $context);

        if (empty($ids)) {
            return null;
        }

        $ids = $this->filterSystemCategories($ids, $context);

        $ids = $this->categoryDepthService->get(
            $context->getShop()->getCategory(),
            $facet->getDepth(),
            $ids
        );

        $categories = $this->categoryService->getList($ids, $context);

        return $this->categoryTreeFacetResultBuilder->buildFacetResult(
            $categories,
            $this->getFilteredIds($criteria),
            $context->getShop()->getCategory()->getId(),
            $facet
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFacet(FacetInterface $facet)
    {
        return $facet instanceof CategoryFacet;
    }

    /**
     * @param array                $ids
     * @param \Shopware\Context\Struct\ShopContext $context
     *
     * @return array
     */
    private function filterSystemCategories(array $ids, ShopContext $context)
    {
        $system = array_merge(
            [$context->getShop()->getCategory()->getId()],
            $context->getShop()->getCategory()->getPath()
        );

        return array_filter($ids, function ($id) use ($system) {
            return !in_array($id, $system);
        });
    }

    /**
     * @param \Shopware\Search\Criteria             $reverted
     * @param \Shopware\Context\Struct\ShopContext $context
     *
     * @return int[]
     */
    private function fetchCategoriesOfProducts(Criteria $reverted, ShopContext $context)
    {
        $query = $this->queryBuilderFactory->createQuery($reverted, $context);
        $query->resetQueryPart('orderBy');
        $query->resetQueryPart('groupBy');
        $query->select(['productCategory.categoryID']);
        $query->innerJoin('product', 's_articles_categories_ro', 'productCategory', 'productCategory.articleID = product.id');
        $query->groupBy('productCategory.categoryID');

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param Criteria $criteria
     *
     * @return int[]
     */
    private function getFilteredIds(Criteria $criteria)
    {
        $active = [];
        foreach ($criteria->getUserConditions() as $condition) {
            if ($condition instanceof CategoryCondition) {
                $active = array_merge($active, $condition->getCategoryUuids());
            }
        }

        return $active;
    }
}
