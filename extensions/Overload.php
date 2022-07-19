<?PHP

namespace extensions;

use IAM\Sso;

use applications\iam\policy\database\Vertex as Policy;

class Overload
{
    public static function run() : void
    {
        $overload = Sso::getRemoteOveloadPolicy();
        if (null !== $overload)
            Policy::setOverload(...$overload);
    }
}
