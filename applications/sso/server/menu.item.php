<?PHP

namespace applications\sso\server;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('dns');
    $item->setViewsFavorite('read');
});
