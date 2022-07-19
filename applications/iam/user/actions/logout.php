<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;

use applications\iam\user\database\Vertex as User;

use extensions\Navigator;

User::logout();

Output::concatenate(Navigator::RETURN_URL, Navigator::getUrl());
Output::print(true);
