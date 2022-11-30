<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Request;

use applications\iam\policy\database\Vertex as Policy;

$filters_post = Request::post();
$filters = array();
array_push($filters, chr(37));

$filters = array_diff((array)$filters_post, $filters);
$filters = array_filter($filters, function ($item) {
    return is_string($item) && strlen($item);
});

if (empty($filters)) Output::print(false);
Policy::mandatories(...$filters);
Output::print(true);
