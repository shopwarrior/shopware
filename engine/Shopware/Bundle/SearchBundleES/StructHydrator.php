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

namespace Shopware\Bundle\SearchBundleES;

use Shopware\Bundle\StoreFrontBundle\Common\Attribute;
use Shopware\Media\Struct\Media;
use Shopware\MediaThumbnail\Struct\MediaThumbnail;
use Shopware\Bundle\StoreFrontBundle\Property\PropertyGroup;
use Shopware\Bundle\StoreFrontBundle\Property\PropertyOption;

/**
 * Class StructHydrator
 */
class StructHydrator
{
    /**
     * @param array $data
     *
     * @return PropertyGroup
     */
    public function createPropertyGroup($data)
    {
        $group = new PropertyGroup();
        $group->setId($data['id']);
        $group->setName($data['name']);
        $group->setFilterable($data['filterable']);

        $me = $this;
        $options = array_map(function ($temp) use ($me) {
            return $me->createPropertyOption($temp);
        }, $data['options']);

        $group->setOptions($options);
        $group->addAttributes($this->createAttributes($data['attributes']));

        return $group;
    }

    /**
     * @param array $data
     *
     * @return PropertyOption
     */
    public function createPropertyOption($data)
    {
        $option = new PropertyOption();
        $option->setId($data['id']);
        $option->setName($data['name']);
        $option->setPosition($data['position']);

        if ($data['media']) {
            $option->setMedia($this->createMedia($data['media']));
        }
        $option->addAttributes($this->createAttributes($data['attributes']));

        return $option;
    }

    /**
     * @param array $data
     *
     * @return \Shopware\Media\Struct\Media
     */
    public function createMedia($data)
    {
        $media = new Media();
        $media->setId($data['id']);
        $media->setExtension($data['extension']);
        $media->setName($data['name']);
        $media->setPreview($data['preview']);
        $media->setType($data['type']);
        $media->setFile($data['file']);
        $media->setDescription($data['description']);

        $me = $this;
        $thumbnails = array_map(function ($thumbnailData) use ($me) {
            return $me->createThumbnail($thumbnailData);
        }, $data['thumbnails']);

        $media->setThumbnails($thumbnails);
        $media->addAttributes($this->createAttributes($data['attributes']));

        return $media;
    }

    /**
     * @param array $data
     *
     * @return \Shopware\MediaThumbnail\Struct\MediaThumbnail
     */
    public function createThumbnail($data)
    {
        $thumbnail = new MediaThumbnail(
            $data['source'],
            $data['retinaSource'],
            $data['maxWidth'],
            $data['maxHeight']
        );
        $thumbnail->addAttributes($this->createAttributes($data['attributes']));

        return $thumbnail;
    }

    /**
     * @param array $data
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Common\Attribute[]
     */
    public function createAttributes($data)
    {
        $attributes = [];
        foreach ($data as $key => $value) {
            $attributes[$key] = new Attribute($value);
        }

        return $attributes;
    }
}
