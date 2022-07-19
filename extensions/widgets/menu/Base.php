<?PHP

namespace extensions\widgets\menu;

use Menu\Base as MenuBase;

use applications\sso\cluster\database\Vertex as Cluster;
use applications\sso\application\database\Vertex as Application;

class Base extends MenuBase
{
    public static function createFromDatabase(Cluster $cluster, Application $application) : Base
    {
        $instance = static::create();
        $instance_admitted = array_fill_keys(static::FRONT_MENU_PARAMETERS, null);

        $instance_cluster = $cluster->getAllFieldsValues(true);
        $instance_cluster = array_intersect_key($instance_cluster, $instance_admitted);
        $instance_cluster['cluster'] = $cluster->getLabel();
        $instance->setFromAssociative($instance_cluster);

        $instance_application = $application->getAllFieldsValues(true);
        $instance_application = array_intersect_key($instance_application, $instance_admitted);
        $instance_application['label'] = $application->getLabel();
        $instance->setFromAssociative($instance_application);

        $application_basename = $application->getField('basename')->getValue();
        $application_basename_path = APPLICATIONS . $application_basename;

        $instance->setAvailableModules($application_basename_path);

        return $instance;
    }
}
