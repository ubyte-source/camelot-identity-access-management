<?PHP

namespace applications\iam\group;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('group');
    $item->setViewsProtected('assignment');
    $item->setViewsFavorite('read');
});
