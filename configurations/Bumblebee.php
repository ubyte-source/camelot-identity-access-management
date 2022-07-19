<?PHP

namespace configurations;

use Knight\Lock;

final class Bumblebee
{
	use Lock;

	const APIKEY = ENVIRONMENT_GOOGLE_APIKEY_TEXTTOSPEECH;
	const LOCALE = [
		'it' => 'it-it',
		'en' => 'en-gb',
		'de' => 'de-de',
		'es' => 'es-es',
		'fr' => 'fr-fr'
	];
}
