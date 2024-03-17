# bitrixSections

I think this is missing in Bitrix...

### Use:

```php
use \Ms\General\Iblock\Sections;

Sections::getInstance(MAIN_CATALOG_IBLOCK_ID)->get()['path'][888],
Sections::getInstance(MAIN_CATALOG_IBLOCK_ID)->getEl(888)
```