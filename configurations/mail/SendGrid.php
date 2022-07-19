<?PHP

namespace configurations\mail;

use Knight\Lock;

final class SendGrid
{
	use Lock;

	const KEY = ENVIRONMENT_SENDGRID_APIKEY;
}
