<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           ControllerAnonymousMail
 * Description:     Controller ControllerAnonymousMail
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         15/07/2020
 */
class ControllerAnonymousMail
{
    use MixinsUtilsCaptcha,
        MixinsUtilsSendmail;

    private static $instance;
    private function __construct()
    {
        $this->pdo_db = ApiDatabase::__instance_singleton()->pdo_useDB();
    }

    /**
     * Type SETTER
     * Instance la Class directement dans la class.
     *
     * return
     */
    public static function __instance_modular_singleton(string $method)
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        self::$instance->router();
    }

    private function router()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('anonymous/send/mail')) {
                    $this->customMessage();
                }
                break;
            case 'PUT': // Mise à jour des données
                break;
            case 'DELETE': // Suppression de données
                break;
        }

    }

    private function customMessage()
    {
        $input = $this->checkInput();

        if (!$this->isCaptchaPassed()) {
            ApiCacheData::__instance_singleton()->add_check_input(['captcha_failed']);
        } else if (
            isset($input['name']) &&
            isset($input['phone']) &&
            isset($input['email']) &&
            isset($input['message'])
        ) {

            $objEmail = 'Vous avez reçu un message de ' . $input['name'];

            $htmlEmail = '
                <!DOCTYPE html><html lang="fr"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><style>html{background:#333;color:#fff}fieldset{overflow-wrap:break-word;word-break:break-word}textarea{margin:0;width:100%;resize:none}h1{text-align:left;margin:0}a{color:white}</style></head><body><div><h1>Bonjour,</h1><p> <br />vous avez reçu un message privé par le biais du site web ' . $_SERVER['SERVER_NAME'] . ', <br /><h4>voici ses coordonnées:</h4></p><ul><li>Nom & prénom: ' . $input['name'] . '</li><li>Téléphone: ' . $input['phone'] . '</li><li>Email: ' . $input['email'] . '</li></ul> <br /><br /> <strong>Son message:</strong><pre> ' . $input['message'] . ' </pre>' . $this->generateFooter(true) . '</div></body></html>
            ';
            $textEmail = '
                Bonjour,

                vous avez reçu un message privé par le biais du site web ' . $_SERVER['SERVER_NAME'] . ',

                voici ses coordonnées:
                Nom & prénom: ' . $input['name'] . '
                Téléphone: ' . $input['phone'] . '
                Email: ' . $input['email'] . '

                Son message:

                ' . $input['message'] . '
                ' . $this->generateFooter(false) . '
            ';

            $this->writeMailMessage($input['email'], $objEmail, $htmlEmail, $textEmail);
        } else {
            ApiCacheData::__instance_singleton()->add_check_input(['something_is_wrong']);
        }
    }

    /**
     * Type GETTER
     * Verifie les champs envoyés par l'utilisateur.
     *
     * return {array}
     */
    private function checkInput()
    {
        $REQ_DATA = ApiMisc::REQ_DATA();
        $update = array();

        if (isset($REQ_DATA['name'])) {
            $data = ApiMisc::sanitize_string($REQ_DATA['name']);
            if (strlen($data) > 0 && strlen($data) <= 80) {
                $update['name'] = $data;
            } else {
                ApiCacheData::__instance_singleton()->add_check_input(['name_too_long']);
            }
        }

        if (isset($REQ_DATA['phone'])) {
            $data = ApiMisc::sanitize_string($REQ_DATA['phone']);
            if (strlen($data) <= 20) {
                $update['phone'] = $data;
            } else {
                ApiCacheData::__instance_singleton()->add_check_input(['phone_invalid']);
            }
        }

        if (isset($REQ_DATA['email'])) {
            if (filter_var($REQ_DATA['email'], FILTER_VALIDATE_EMAIL)) {
                $update['email'] = ApiMisc::sanitize_string($REQ_DATA['email']);
            } else {
                ApiCacheData::__instance_singleton()->add_check_input(['email_not_valid']);
            }
        }

        if (isset($REQ_DATA['message'])) {
            $data = ApiMisc::sanitize_string($REQ_DATA['message']);
            if (strlen($data) <= 500) {
                $update['message'] = $data;
            } else {
                ApiCacheData::__instance_singleton()->add_check_input(['message_too_long']);
            }
        }

        return $update;
    }

}