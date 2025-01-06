<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\Datasource\EntityNew;

class UserProps extends EntityNew
{
    protected $id;
    protected $name;
    protected $age;
    protected $phones;
}
