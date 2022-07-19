<?PHP

namespace applications\sso\oauth;

use extensions\widgets\menu\Base as Menu;

return Menu::create(function (Menu $item) {
    $item->getField('icon')->setValue('apps_outage');
    $item->setViewsFavorite('read');
});
