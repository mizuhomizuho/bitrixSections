<?php

namespace General\Iblock;

class Sections {

    private int $iblockId;
    private ?array $base = null;
    private array $getTreeBasePath = [];
    private array $getTreeBaseCodePath = [];

    private static array $instance = [];

    private function __construct() {}

    static function getInstance(int $iblockId)
    {
        if (!isset(static::$instance[$iblockId])) {

            static::$instance[$iblockId] = new static;
            static::$instance[$iblockId]->iblockId = $iblockId;
        }

        return static::$instance[$iblockId];
    }

    function get(): array
    {
        if ($this->base === null) {

            $cache = \Bitrix\Main\Application::getInstance()->getManagedCache();

            $cacheId = __CLASS__ . '::' . __FUNCTION__;

            if ($cache->read(3600 * 24 * 365 * 888, $cacheId)) {

                $this->base = $cache->get($cacheId);

            } else {

                $this->base = [
                    'tree' => $this->sort($this->getTreeBase()),
                    'path' => $this->getTreeBasePath,
                    'codePath' => $this->getTreeBaseCodePath,
                ];

                $this->setCount();

                $cache->set($cacheId, $this->base);
            }
        }

        return $this->base;
    }

    private function setCount(): void
    {
        $iblockId = $this->iblockId;
        $stack = array();
        $entity = \Bitrix\Iblock\Model\Section::compileEntityByIblock($iblockId);
        $rsSection = $entity::getList(array(
            'order' => array(
                'DEPTH_LEVEL' => 'DESC',
            ),
            'filter' => array(
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
            ),
            'select' =>  array(
                'ID',
                'DEPTH_LEVEL',
                'IBLOCK_SECTION_ID',
            ),
        ));

        while ($section=$rsSection->fetch())
        {
            $resultCount = \Bitrix\Iblock\SectionElementTable::getList(array(
                'runtime' => array(
                    new \Bitrix\Main\ORM\Fields\ExpressionField('COUNT',  'COUNT(*)' )
                ),
                'filter' => array(
                    'IBLOCK_SECTION_ID' => $section['ID'],
                    'IBLOCK_ELEMENT.ACTIVE' => 'Y',
                    'IBLOCK_ELEMENT.IBLOCK_ID' => $iblockId,
                ),
                'select' => array(
                    'COUNT'
                ),
            ));

            $count = 0;
            if($elementCount=$resultCount->fetch())
                $count = (int)$elementCount['COUNT'];

            if(!array_key_exists((int)$section['IBLOCK_SECTION_ID'],$stack))
                $stack[(int)$section['IBLOCK_SECTION_ID']] = array('count'=>0,'recursiveCount'=>0,'subsections'=>0);
            if(!array_key_exists((int)$section['ID'],$stack))
                $stack[(int)$section['ID']] = array('count'=>0,'recursiveCount'=>0,'subsections'=>0);

            $stack[(int)$section['IBLOCK_SECTION_ID']]['subsections']++;
            $stack[(int)$section['ID']]['count'] = $count;
            $stack[(int)$section['ID']]['section'] = $section;
        }

        $getParentId = function($section,$chain=array()) use (&$getParentId,$stack)
        {
            if(!($parentSectionId=(int)$section['section']['IBLOCK_SECTION_ID']) || !($parentSection=$stack[$parentSectionId]))
                return $chain;
            $chain[] = $parentSectionId;
            $chain = $getParentId($parentSection,$chain);
            return $chain;
        };

        foreach($stack as $sectionId=>$section)
        {
            $stack[$sectionId]['recursiveCount'] += $section['count'];
            $parentIds = $getParentId($section);
            foreach($parentIds as $parentId)
            {
                $stack[$parentId]['recursiveCount'] += $section['count'];
            }
        }

        foreach($stack as $sectionId=>$section)
        {
            if (!isset($this->base['path'][$sectionId])) {
                $this->base['tree'][$sectionId]['count'] = $section['count'];
                $this->base['tree'][$sectionId]['recursiveCount'] = $section['recursiveCount'];
            }
            else {
                eval('$this->base[\'tree\'][' .
                    implode('][\'children\'][', $this->base['path'][$sectionId]) .
                    '][\'children\'][' . $sectionId . '][\'count\'] = $section[\'count\'];');
                eval('$this->base[\'tree\'][' .
                    implode('][\'children\'][', $this->base['path'][$sectionId]) .
                    '][\'children\'][' . $sectionId . '][\'recursiveCount\'] = $section[\'recursiveCount\'];');
            }
        }
    }

    private function sort(array $tree): array
    {
        $forSort = [];
        foreach ($tree as $treeItem) {
            $forSort[$treeItem['el']['ID']] = $treeItem['el']['SORT'];
        }
        asort($forSort);
        $treeSorted = [];
        foreach ($forSort as $elK => $sort) {

            $el = $tree[$elK];

            $treeSorted[$elK] = $el;

            if (isset($el['children'])) {
                $fns = __FUNCTION__;
                $treeSorted[$elK]['children'] = $this->$fns($el['children']);
            }
        }

        return $treeSorted;
    }

    function getByCode(string $code): false|array
    {
        $sections = $this->get();

        foreach ($sections['tree'] as $section) {
            if ($section['el']['CODE'] === $code) {
                return $section;
            }
        }

        foreach ($sections['codePath'] as $id => $path) {
            $pathExpl = explode('/', $path);
            if ($pathExpl[count($pathExpl) - 1] === $code) {
                return $this->getEl($id);
            }
        }

        return false;
    }

    function getEl(string|int $sectionId): false|array
    {
        $sections = $this->get();

        if (!isset($sections['path'][$sectionId])) {
            if (isset($sections['tree'][$sectionId])) {
                return $sections['tree'][$sectionId];
            }
            else {
                return false;
            }
        }

        return eval('return $sections[\'tree\'][' .
            implode('][\'children\'][', $sections['path'][$sectionId]) .
            '][\'children\'][' . $sectionId . '];');
    }

    private function getTreeBase(

        array &$list = [],
        ?string $id = null,
        array $path = [],
        string $codePath = '',

    ): array {

        $return = [];

        if ($id === null) {

            $list = \Bitrix\Iblock\SectionTable::query()
                ->setSelect([
                    'ID',
                    'IBLOCK_SECTION_ID',
                    'SORT',
                    'NAME',
                    'CODE',
                ])
                ->where('IBLOCK_ID', '=', $this->iblockId)
                ->where('ACTIVE', '=', 'Y')
                ->fetchAll();
        }

        foreach($list as $listK => $listV) {

            if($listV['IBLOCK_SECTION_ID'] === $id) {

                if ($path === []) {

                    $catPath = [$id];
                    $catCodePath = '/' . $listV['CODE'];
                }
                else {

                    $catPath = $path;
                    $catPath[] = $id;

                    $catCodePath = $codePath;
                    $catCodePath .= '/' . $listV['CODE'];
                }

                if($catPath === [null]) {

                    $catPath = [];
                    $catCodePath = '';
                }
                else {

                    $this->getTreeBasePath[$listV['ID']] = $catPath;
                    $this->getTreeBaseCodePath[$listV['ID']] = $catCodePath . '/' . $listV['CODE'];
                }

                $return[$listV['ID']]['el'] = $listV;
                unset($list[$listK]);

                $fns = __FUNCTION__;

                $return[$listV['ID']]['children'] = $this->$fns($list, $listV['ID'], $catPath, $catCodePath);

                if(!count($return[$listV['ID']]['children'])) {

                    unset($return[$listV['ID']]['children']);
                }
            }
        }

        return $return;
    }
}
