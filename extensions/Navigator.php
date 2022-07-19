<?PHP

namespace extensions;

use IAM\Request as IAMRequest;

use Knight\armor\Cipher;
use Knight\armor\Request;
use Knight\armor\Navigator as KNavigator;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\database\edges\ApplicationToServer;

use extensions\Overload;

class Navigator extends KNavigator
{
    const ENABLE = true;

    protected static $impersonate = false;

    public static function setImpersonate(bool $status) : void
    {
        static::$impersonate = $status === static::ENABLE;
    }

    public static function getFingerprint() : string
    {
        $user_agent = static::getUserAgent();
        $user_agent_internet_protocol = static::getClientIPVerified();
        $user_fingerprint = $user_agent
            . chr(126)
            . $user_agent_internet_protocol;
        $user_fingerprint = md5($user_fingerprint);
        return $user_fingerprint;
    }

    public static function cidrMatch(int $ip, string $cidr) : bool
    {
        list($subnet, $mask) = explode(chr(47), $cidr);

        $integer_subnet = ip2long($subnet);

        $subnet_mask = $mask ?? 32;
        $subnet_mask = 32 - $subnet_mask;
        $subnet_mask = 1 << $subnet_mask;
        $subnet_mask = $subnet_mask - 1;
        $subnet_mask = ~$subnet_mask;
        $subnet_mask_operation = $ip & $subnet_mask;
        return $integer_subnet === $subnet_mask_operation;
    }

    public static function checkRemoteIP(string $ip, string ...$cidr_group) : bool
    {
        foreach ($cidr_group as $cidr)
            if (static::cidrMatch($ip, $cidr))
                return true;
        return false;
    }

    public static function getClientIPVerified() : int
    {
        if (!!static::getImpersonate()) return parent::getClientIP();

        $ip = parent::getClientIP();

        $exceed = Request::header(IAMRequest::HEADER_OVERRIDE);
        $header_application = Request::header(IAMRequest::HEADER_APPLICATION);

        if (null === $exceed
            || null === $header_application) return $ip;

        $application = new Application();
        $application->getField(Arango::KEY)->setSafeModeDetached(false)->setValue($header_application);
        $application_query = ArangoDB::start($application);
        $application_server = $application->useEdge(ApplicationToServer::getName())->vertex();
        $application_server_cidr_name = $application_server->getField('cidr');
        $application_server_cidr_name = $application_server_cidr_name->getName();
        $application_query_select = $application_query->select();
        $application_query_select_main_iteration_vertex = $application_query_select->getPointer(Choose::VERTEX);
        $application_query_select_return = 'COLLECT AGGREGATE a = UNIQUE' . chr(40) . $application_query_select_main_iteration_vertex . chr(46) . $application_server_cidr_name . chr(41) . chr(32) . 'RETURN FLATTEN(a)';
        $application_query_select->getReturn()->setPlain($application_query_select_return);
        $application_query_select_response = $application_query_select->run();
        $application_query_select_response = reset($application_query_select_response);
        if (false === $application_query_select_response
            || !static::checkRemoteIP($ip, ...$application_query_select_response)) static::exception();

        Overload::run();

        return $exceed;
    }

    protected static function getImpersonate() : bool
    {
        return static::$impersonate;
    }
}
