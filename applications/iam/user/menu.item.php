<?PHP

namespace applications\iam\user;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('person');
    $item->getField('priority')->setValue(100);
    $item->setViewsProtected('login', 'assignment');
    $item->setViewsFavorite('read');
});
