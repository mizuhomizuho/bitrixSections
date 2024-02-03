<?php

namespace Ms\General\Iblock;

class Sections {

    private int $iblockId;
    private ?array $base = null;
    private array $getTreeBasePath = [];

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

            $this->base = [
                'tree' => $this->getTreeBase(),
                'path' => $this->getTreeBasePath,
            ];
        }

        return $this->base;
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

    ): array {

        $return = [];

        if ($id === null) {

            $list = \Bitrix\Iblock\SectionTable::query()
                ->setCacheTtl(3600 * 24)
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
                }
                else {

                    $catPath = $path;
                    $catPath[] = $id;
                }

                if($catPath === [null]) {

                    $catPath = [];
                }
                else {

                    $this->getTreeBasePath[$listV['ID']] = $catPath;
                }

                $return[$listV['ID']]['el'] = $listV;
                unset($list[$listK]);

                $fns = __FUNCTION__;

                $return[$listV['ID']]['children'] = $this->$fns($list, $listV['ID'], $catPath);

                if(!count($return[$listV['ID']]['children'])) {

                    unset($return[$listV['ID']]['children']);
                }
            }
        }

        return $return;
    }
}