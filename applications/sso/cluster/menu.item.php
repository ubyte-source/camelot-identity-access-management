<?PHP

namespace applications\sso\cluster;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('cloud_queue');
    $item->setViewsFavorite('read');
});
