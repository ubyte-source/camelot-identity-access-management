<?PHP

namespace applications\sso\application;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('apps');
    $item->getField('priority')->setValue(100);
    $item->setViewsFavorite('read');
});
