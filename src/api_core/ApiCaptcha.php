<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           ApiCaptcha.php
 * Description:     Captcha personnalisé.
 *                  Permet de verifier si un captcha est requis pour continuer, retourne un identifiant à 4 caractères,
 *                  peut lire, mettre à jour et valider le captcha.
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         24/05/2020
 */
class ApiCaptcha
{
    private static $SETTING = [
        'number' => 4,
        'width' => 200,
        'height' => 50,
        'quality' => 70,
        'char_available' => '24689?tykjf',
        'font_type_path' => '/api_core/font/destroy.ttf',
    ];

    /**
     * Type SETTER
     * Class __instance_singleton
     * Instance la Class directement dans la class une unique fois.
     *
     * return {object} Instance
     */
    private static $singleton = false, $instance = null;
    private function __construct()
    {}
    public static function __instance_singleton()
    {
        if (!self::$singleton) {
            self::$instance = new self();
            self::$instance->require_table();
            self::$instance->purge_captcha();
        }

        return self::$instance;
    }

    /**
     * Type SETTER
     * Verifie que les tables requises sont presentes, les ajoutes le cas échéant.
     *
     * no return
     */
    private function require_table()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "captcha` (
                `remote_ip` varchar(80) NOT NULL DEFAULT '',
                `picture` blob,
                `code` varchar(10) NOT NULL DEFAULT '',
                `user_target` varchar(50) DEFAULT NULL,
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_test` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `remote_ip` (`remote_ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }

    /**
     * Type SETTER
     * Purge les vieux captcha existant non renseignés,
     * purge tous les captcha supérieur à 7 jours
     *
     * no return
     */
    private function purge_captcha()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("DELETE FROM " . Config::Database()['prefix'] . "captcha WHERE DATEDIFF( CURRENT_TIMESTAMP, created) > 7;");
    }

    public function router()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        if ($api_token->isValidated()) {
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET': // Lecture de données
                    if (ApiMisc::isRouteUsed('api/captcha/ask')) {
                        $this->addCaptcha();
                    }
                    break;
                case 'POST': // Création/Ajout de données
                    break;
                case 'PUT': // Mise à jour des données
                    if (ApiMisc::isRouteUsed('api/captcha/change')) {
                        $this->changeCaptcha($api_cache_data, $api_token);
                    }
                    break;
                case 'DELETE': // Suppression de données
                    if (ApiMisc::isRouteUsed('api/captcha/validate')) {
                        // $this->verifyCaptcha($api_cache_data, $api_token);
                    }
                    break;
            }
        }
    }

    /**
     * Type GETTER
     * Fabrique un captcha avec la librairie GD PHP
     * retour cette image jpeg encodé en base 64
     *
     * return {array} - [code] le code captcha
     *                - [base64] l'image contenant le code captcha encodé en base 64
     */
    private function create_captcha()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');

        $image = imagecreatetruecolor(self::$SETTING['width'], self::$SETTING['height']);

        imagefilledrectangle($image, 0, 0, self::$SETTING['width'], self::$SETTING['height'], imagecolorallocate($image, 33, 33, 33));

        $random_char = strtolower(substr(str_shuffle(self::$SETTING['char_available']), 0, self::$SETTING['number']));

        $captcha_spaced = "";
        for ($i = 0; $i <= strlen($random_char); $i++) {
            $captcha_spaced .= substr($random_char, $i, 1) . " ";
        }

        $font_type = pathinfo($_SERVER['SCRIPT_FILENAME']);
        $font_type = $font_type['dirname'] . self::$SETTING['font_type_path'];

        if (file_exists($font_type)) {
            for ($i = 0; $i <= 5; $i++) {
                $color = array(
                    [255, 0, 0],
                    [255, 255, 0],
                    [0, 255, 255],
                );
                $color_rnd = mt_rand(0, 2);

                imagerectangle($image, rand(1, self::$SETTING['width'] - 25), rand(1, self::$SETTING['height']), rand(1, self::$SETTING['width'] + 25), rand(1, self::$SETTING['height']), imagecolorallocate($image,
                    $color[$color_rnd][0], $color[$color_rnd][1], $color[$color_rnd][2])
                );
            }
            imagettftext($image, 22, 0, 5, (self::$SETTING['height'] / 1.5), imagecolorallocate($image, mt_rand(240, 255), mt_rand(120, 255), mt_rand(100, 255)), $font_type, $captcha_spaced);
            for ($i = 0; $i <= 5; $i++) {
                imagerectangle($image, rand(1, self::$SETTING['width'] - 25), rand(1, self::$SETTING['height']), rand(1, self::$SETTING['width'] + 25), rand(1, self::$SETTING['height']), imagecolorallocate($image,
                    mt_rand(100, 255), mt_rand(100, 255), mt_rand(0, 255))
                );
            }
        } else {
            imagestring($image, 5, 10, 0, $captcha_spaced, imagecolorallocate($image, 255, 255, 255)); // Si le true type ne fonctionne pas alors on affiche sans true type.
        }

        imagejpeg($image, $tmp, self::$SETTING['quality']);
        imagedestroy($image);

        $data = base64_encode(file_get_contents($tmp));
        @unlink($tmp);

        return array(
            'code' => $random_char,
            'base64' => 'data:image/jpeg;base64,' . $data,
        );
    }

    /**
     * Type GETTER
     * Verifie si une demande de captcha est presente
     * retourne false si presente ou true si aucune verif nécessaire.
     *
     * return {bool}
     */
    public function verifyCaptcha()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'captcha', ['remote_ip' => ApiMisc::remote_addr()], [], 1, 0);
        if (count($req)) {
            $REQ_DATA = ApiMisc::REQ_DATA();

            $captcha_code = isset($REQ_DATA['captcha']) ? ApiMisc::sanitize_string($REQ_DATA['captcha']) : false;
            if ($captcha_code && strtolower($req[0]['code']) === strtolower($captcha_code)) {
                //   if (1 === 1) {
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'captcha', ['remote_ip' => ApiMisc::remote_addr()]);

                $api_token->setPayload(array(
                    'content' => [
                        'id' => -1,
                        'loggedIn' => false,
                        'rank' => 'guest',
                        'firstName' => '',
                        'lastName' => '',
                        'email' => '',
                        'captchaPassed' => true,
                    ],
                ));

                $api_cache_data->add($api_token->newToken($api_cache_data));

                return true;
            } else {
                $api_cache_data->add_check_input(['captcha_not_valid']);
                $api_cache_data->add(array(
                    'picture' => $req[0]['picture'],
                    // 'code' => $req[0]['code'], // pour debogage/test ne pas laisser ou commenter la ligne
                ));

                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Type SETTER
     * Verifie si une demande de captcha est presente
     * retourne false si presente ou true si aucune verif nécessaire.
     *
     * no return
     */
    public function addCaptcha()
    {
        if (ApiMisc::getMyId() <= 0) {
            $api_cache_data = ApiCacheData::__instance_singleton();
            $api_token = ApiToken::__instance_singleton();

            $create_captcha = $this->create_captcha();

            $REQ_DATA = ApiMisc::REQ_DATA();
            $username = isset($REQ_DATA['username']) ? ApiMisc::sanitize_string($REQ_DATA['username']) : '';

            $insert = array(
                'remote_ip' => ApiMisc::remote_addr(),
                'picture' => $create_captcha['base64'],
                'code' => $create_captcha['code'],
                'user_target' => substr($username, 0, 30),
            );

            if (!ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'captcha', $insert)) {
                $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'captcha', ['remote_ip' => ApiMisc::remote_addr()], [], 1, 0);
                if (count($req)) {
                    $api_cache_data->add(array(
                        'picture' => $req[0]['picture'],
                        // 'code' => $req[0]['code'], // pour debogage/test ne pas laisser ou commenter la ligne
                    ));
                }
            } else {
                $api_cache_data->add(array(
                    'picture' => $create_captcha['base64'],
                    // 'code' => $create_captcha['code'], // pour debogage/test ne pas laisser ou commenter la ligne
                ));
            }

            ApiMisc::http_response_code(401);
            // Action à faire pour login incorrecte risque de brute force mot de passe
        }
    }

    /**
     * Type SETTER
     * Remplace le captcha existant par un nouveau uniquement si il en existe un pour l'adresse IP cliente
     * retourne true si changement réussi
     * retourne false si aucun captcha present pour l'adresse IP cliente
     *
     * return {bool}
     */
    private function changeCaptcha()
    {
        if (ApiMisc::getMyId() <= 0) {
            $api_cache_data = ApiCacheData::__instance_singleton();
            $api_token = ApiToken::__instance_singleton();

            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'captcha', ['remote_ip' => ApiMisc::remote_addr()], [], 1, 0);
            if (count($req)) {
                $create_captcha = $this->create_captcha();

                $set = array(
                    'picture' => $create_captcha['base64'],
                    'code' => $create_captcha['code'],
                );
                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'captcha', $set, ['remote_ip' => ApiMisc::remote_addr()], 1);

                $api_cache_data->add(array(
                    'picture' => $create_captcha['base64'],
                    // 'code' => $create_captcha['code'], // pour debogage/test ne pas laisser ou commenter la ligne
                ));
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}