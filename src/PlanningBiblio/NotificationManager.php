<?php

namespace App\PlanningBiblio;
use App\Model\UserToken;
use App\PlanningBiblio\Helper\UserTokenHelper;

class NotificationManager
{

    private $config;
    private $helper;

    public function __construct()
    {
        $this->config = $GLOBALS['config'];
        $this->helper = new UserTokenHelper();
    }

    /* Gets or create a user token */
    public function getUserToken()
    {
        // Purge old tokens
        $token = new UserToken();
        $token_string = $this->helper->generateToken();
        error_log('token string : ' . $token_string);
        $token->token($token_string);
        $token->created(new \DateTime());
        return $token_string;
    }

    private function purgeOldTokens() {

    }

}
