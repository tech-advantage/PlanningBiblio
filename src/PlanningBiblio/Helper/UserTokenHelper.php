<?php

namespace App\PlanningBiblio\Helper;

use App\PlanningBiblio\Helper\BaseHelper;

use App\Model\UserToken;

class UserTokenHelper extends BaseHelper
{

    public function __construct()
    {
        parent::__construct();
    }

    public function generateToken()
    {
         $random = random_bytes(255);
         return hash('sha256', $random);
    }
}
