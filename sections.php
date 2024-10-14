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
                    'tree' => $this->sortAndCalcCount($this->getTreeBase()),
                    'path' => $this->getTreeBasePath,
                    'codePath' => $this->getTreeBaseCodePath,
                ];

                $cache->set($cacheId, $this->base);
            }
        }

        return $this->base;
    }

    private function sortAndCalcCount(array $tree): array
    {
        usort($tree, fn($a, $b) => strcmp($a['el']['SORT'], $b['el']['SORT']));

        foreach ($tree as $elK => $el) {

            if (isset($el['children'])) {
                $fns = __FUNCTION__;
                $tree[$elK]['children'] = $this->$fns($el['children']);
            }

            $return[$listV['ID']]['count'] = \Bitrix\Iblock\SectionElementTable::query()
                ->setSelect(['COUNT_ALL'])
                ->registerRuntimeField(
                    '',
                    new \Bitrix\Main\ORM\Fields\ExpressionField(
                        'COUNT_ALL',
                        'COUNT(*)',
                    )
                )
                ->registerRuntimeField(
                    'els',
                    [
                        'data_type' => \Bitrix\Iblock\ElementTable::class,
                        'reference' => [
                            '=ref.ID' => 'this.IBLOCK_ELEMENT_ID',
                        ],
                    ]
                )
                ->where('IBLOCK_SECTION_ID', '=', $listV['ID'])
                ->where('els.ACTIVE', '=', 'Y')
                ->where('els.IBLOCK_ID', '=', $this->iblockId)
                ->fetch()['COUNT_ALL'];
        }

        return $tree;
    }

    function getEl(string|int $sectionId): array
    {
        $sections = $this->get();

        if (!isset($sections['path'][$sectionId])) {
            return $sections['tree'][$sectionId];
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
                    $this->getTreeBaseCodePath[$listV['ID']] = $catCodePath;
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
