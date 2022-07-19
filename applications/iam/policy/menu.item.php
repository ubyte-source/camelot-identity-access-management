<?PHP

namespace applications\iam\policy;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('gavel');
    $item->setViewsFavorite('read');
});
