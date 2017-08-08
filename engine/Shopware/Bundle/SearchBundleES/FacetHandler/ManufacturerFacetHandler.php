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

namespace Shopware\Bundle\SearchBundleES\FacetHandler;

use ONGR\ElasticsearchDSL\Aggregation\TermsAggregation;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Bundle\SearchBundle\Facet\ManufacturerFacet;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\SearchBundleES\HandlerInterface;
use Shopware\Bundle\SearchBundleES\ResultHydratorInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerServiceInterface;
use Shopware\Components\QueryAliasMapper;

class ManufacturerFacetHandler implements HandlerInterface, ResultHydratorInterface
{
    const AGGREGATION_SIZE = 1000;

    /**
     * @var \Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerServiceInterface
     */
    private $manufacturerService;

    /**
     * @var \Shopware_Components_Snippet_Manager
     */
    private $snippetManager;

    /**
     * @var QueryAliasMapper
     */
    private $queryAliasMapper;

    /**
     * @param \Shopware\Bundle\StoreFrontBundle\Manufacturer\ManufacturerServiceInterface $manufacturerService
     * @param \Shopware_Components_Snippet_Manager                                        $snippetManager
     * @param QueryAliasMapper                                                            $queryAliasMapper
     */
    public function __construct(
        ManufacturerServiceInterface $manufacturerService,
        \Shopware_Components_Snippet_Manager $snippetManager,
        QueryAliasMapper $queryAliasMapper
    ) {
        $this->manufacturerService = $manufacturerService;
        $this->snippetManager = $snippetManager;
        $this->queryAliasMapper = $queryAliasMapper;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(CriteriaPartInterface $criteriaPart)
    {
        return $criteriaPart instanceof ManufacturerFacet;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(
        CriteriaPartInterface $criteriaPart,
        Criteria $criteria,
        Search $search,
        ShopContext $context
    ) {
        $aggregation = new TermsAggregation('manufacturer');
        $aggregation->setField('manufacturer.id');
        $aggregation->addParameter('size', self::AGGREGATION_SIZE);
        $search->addAggregation($aggregation);
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(
        array $elasticResult,
        ProductNumberSearchResult $result,
        Criteria $criteria,
        ShopContext $context
    ) {
        if (!isset($elasticResult['aggregations']['manufacturer'])) {
            return;
        }

        $buckets = $elasticResult['aggregations']['manufacturer']['buckets'];

        if (empty($buckets)) {
            return;
        }

        $ids = array_column($buckets, 'key');
        $manufacturers = $this->manufacturerService->getList($ids, $context);

        $items = $this->createListItems($criteria, $manufacturers);

        $criteriaPart = $this->createFacet($criteria, $items);
        $result->addFacet($criteriaPart);
    }

    /**
     * @param Criteria                                                      $criteria
     * @param \Shopware\ProductManufacturer\Struct\ProductManufacturer[] $manufacturers
     *
     * @return array
     */
    private function createListItems(Criteria $criteria, $manufacturers)
    {
        $actives = [];
        /** @var $condition ManufacturerCondition */
        if ($condition = $criteria->getCondition('manufacturer')) {
            $actives = $condition->getManufacturerIds();
        }

        $items = [];
        /** @var $manufacturer \Shopware\ProductManufacturer\Struct\ProductManufacturer */
        foreach ($manufacturers as $manufacturer) {
            $items[] = new ValueListItem(
                $manufacturer->getId(),
                $manufacturer->getName(),
                in_array($manufacturer->getId(), $actives),
                $manufacturer->getAttributes()
            );
        }

        usort($items, function (ValueListItem $a, ValueListItem $b) {
            return strcasecmp($a->getLabel(), $b->getLabel());
        });

        return $items;
    }

    /**
     * @param Criteria $criteria
     * @param $items
     *
     * @return ValueListFacetResult
     */
    private function createFacet(Criteria $criteria, $items)
    {
        if (!$fieldName = $this->queryAliasMapper->getShortAlias('sSupplier')) {
            $fieldName = 'sSupplier';
        }

        /** @var ManufacturerFacet $facet */
        $facet = $criteria->getFacet('manufacturer');
        if ($facet && !empty($facet->getLabel())) {
            $label = $facet->getLabel();
        } else {
            $label = $this->snippetManager->getNamespace('frontend/listing/facet_labels')
                ->get('manufacturer', 'ProductManufacturer');
        }

        return new ValueListFacetResult(
            'manufacturer',
            $criteria->hasCondition('manufacturer'),
            $label,
            $items,
            $fieldName
        );
    }
}
