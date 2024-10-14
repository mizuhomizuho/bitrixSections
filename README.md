# bitrixSections

I think this is missing in Bitrix...

### Use:

```php
Sections::getInstance(MAIN_CATALOG_IBLOCK_ID)->get()['path'][888],
Sections::getInstance(MAIN_CATALOG_IBLOCK_ID)->getEl(888)

$sectionPath = Sections::getInstance($arResult['IBLOCK_ID'])->get()['path'][$arResult['IBLOCK_SECTION_ID']];

if (isset($sectionPath[0])) {
    $parentSectId = $sectionPath[0];
}
else {
    $parentSectId = $arResult['IBLOCK_SECTION_ID'];
}
```
