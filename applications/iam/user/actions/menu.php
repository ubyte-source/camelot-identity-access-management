<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Navigator;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;

use extensions\widgets\menu\Base as Menu;

Policy::mandatories('iam/user/action/menu');

$navigator = [];

$post_navigator = Request::post('navigator');
if (null !== $post_navigator) {
    $navigator_splitted = explode(Navigator::SEPARATOR, $post_navigator);
    $navigator_splitted = array_filter($navigator_splitted);
    array_push($navigator, ...$navigator_splitted);

    if (!!$navigator) {
        $application = new Application();
        $application_field_basename_value = reset($navigator_splitted);
        $application->getField('basename')->setProtected(false)->setValue($application_field_basename_value);
        $application_query = ArangoDB::start($application);
        $application_query_select = $application_query->select();
        $application_query_select->getLimit()->set(1);
        $application_query_select_return = 'RETURN' . chr(32) . $application_query_select->getPointer(Choose::VERTEX);
        $application_query_select->getReturn()->setPlain($application_query_select_return);
        $application_query_select_response = $application_query_select->run();
        if (null === $application_query_select_response
            || empty($application_query_select_response)) Output::print(false);
        
        $application = new Application();
        $application->setReadMode(true);
        $application_value = reset($application_query_select_response);
        $application->setFromAssociative($application_value, $application_value);
        $application_value = $application->getAllFieldsValues(true);

        $header = Menu::create();
        $header->setFromAssociative($application_value);

        $header_output = $header->output();
        $header_output->label = $application->getLabel();
        Output::concatenate('header', $header_output);
    }
}

$menu = User::getMenu(...$navigator);
Output::concatenate(Output::APIDATA, $menu);
Output::print(true);
