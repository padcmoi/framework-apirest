<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Trait MixinsUtilsCaptcha
 * Utilitaire Captcha
 */
trait MixinsUtilsCaptcha
{
    private function isLoggedInCaptcha()
    {
        return ApiMisc::getMyId() <= 0 ? false : true;
    }

    private function isCaptchaPassed()
    {
        return ApiCaptcha::__instance_singleton()->verifyCaptcha() ? true : false;
    }

}