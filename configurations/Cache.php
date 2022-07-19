<?PHP

namespace configurations;

use Knight\Lock;

use Redis\Configuration as Define;

defined('ENVIRONMENT_CACHE_HOST') or define('ENVIRONMENT_CACHE_HOST', '127.0.0.1');
defined('ENVIRONMENT_CACHE_PORT') or define('ENVIRONMENT_CACHE_PORT', 6379);

final class Cache
{
	use Lock;

	const PARAMETERS = [
		// redis host
		Define::CONFIGURATION_HOST => ENVIRONMENT_CACHE_HOST,
		// redis port
		Define::CONFIGURATION_PORT => ENVIRONMENT_CACHE_PORT,
		// redis timout in second
        Define::CONFIGURATION_TIMEOUT => 1,
		// redis TTL
        Define::CONFIGURATION_TTL => ENVIRONMENT_CACHE_TTL,
		// redis application prefix for all keys
		Define::CONFIGURATION_APPLICATION => 'iam',
		// redis crypt passphrase
		Define::CONFIGURATION_PASSPRHASE => ENVIRONMENT_CACHE_HOST_PASSPHRASE
    ];
}
