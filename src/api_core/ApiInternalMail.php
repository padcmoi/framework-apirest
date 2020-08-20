<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           ApiInternalMail.php
 * Description:     Pour l'envoi de Mail avec verification en base de données pour utilisation légitime et non spam pour récupèrer des données
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         17/06/2020
 */
class ApiInternalMail
{
    use PreventSpamMail;

    private $mail, $random_hash;

    /**
     * Type SETTER
     * Class __instance_singleton
     * Instance la Class directement dans la class une unique fois.
     *
     * return {object} Instance
     */
    private static $singleton = false, $instance = null;
    private function __construct()
    {
        $this->PreventSpamMail();
    }
    public static function __instance_singleton()
    {
        if (!self::$singleton) {
            self::$instance = new self();
            self::$instance->require_table();
            self::$instance->initMailConnection();
            self::$instance->purge();
            self::$instance->random_hash = sha1(time() . mt_rand());

            self::$singleton = true;
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

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "internal_mail` (
            `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `id_account` int(11) NOT NULL,
            `type` varchar(80) NOT NULL,
            `can_expire` BOOLEAN NOT NULL DEFAULT TRUE,
            `hash_to_return` varchar(128) NOT NULL,
            `email` varchar(80) NOT NULL,
            `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `hash_to_return` (`hash_to_return`),
            CONSTRAINT `account_internal_mail_id_account` FOREIGN KEY (`id_account`) REFERENCES `" . Config::Database()['prefix'] . "account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }

    public function router()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('api/action/cancel')) {
                    $this->cancelAction();
                }
                if (ApiMisc::isRouteUsed('api/change/password')) {
                    $this->changePassword();
                }
                if (ApiMisc::isRouteUsed('api/account/enable')) {
                    $this->accountEnable();
                }
                if (ApiMisc::isRouteUsed('api/account/confirm/mail')) {
                    $this->confirmMail();
                }
                if (ApiMisc::isRouteUsed('api/mail/unsubscribe')) {
                    $this->unsubscribeMail();
                }
                if (ApiMisc::isRouteUsed('api/account/close')) {
                    $this->closeAccount();
                }
                if (ApiMisc::isRouteUsed('api/recovered/personnal/data')) {
                    $this->getPersonnalData();
                }
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('api/auth/register')) {
                    $id = intval(ApiAuthentification::__instance_singleton()->getState('registered'));
                    $this->insert($id);
                }
                if (ApiMisc::isRouteUsed('api/recovered/password')) {
                    $this->recoverPassword();
                }
                if (ApiMisc::isRouteUsed('api/recovered/personnal/data')) {
                    $this->askPersonnalData();
                }
                break;
            case 'PUT': // Mise à jour des données
                break;
            case 'DELETE': // Suppression de données
                if (ApiMisc::isRouteUsed('api/account/close')) {
                    $this->askCloseAccount();
                }
                break;
        }
    }

    private function initMailConnection()
    {
        $this->mail = new PHPMailer_Main();

        try {
            $this->mail->isSMTP();
            $this->mail->Host = Config::Mail()['Host'];
            $this->mail->SMTPAuth = Config::Mail()['SMTPAuth'];
            $this->mail->SMTPSecure = Config::Mail()['SMTPSecure'];
            $this->mail->Username = Config::Mail()['Username'];
            $this->mail->Password = Config::Mail()['Password'];
            $this->mail->Port = Config::Mail()['Port'];
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ),
            );
            $this->mail->CharSet = 'utf-8';
        } catch (Exception $error) {
            ApiCacheData::__instance_singleton()->add(array('MailConnectionException' => $error->errorMessage()));
            ApiEncoders::__instance_singleton()->router();
            exit;
        }
    }

    /**
     * Type SETTER
     * Purge les vieux captcha existant non renseignés,
     * purge tous les captcha supérieur à 7 jours
     *
     * no return
     */
    private function purge()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("DELETE FROM `" . Config::Database()['prefix'] . "internal_mail` WHERE can_expire = 1 AND TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, created)) > 1800;");
    }

    /**
     * Type GETTER
     * Génére le pied de page de chaque email
     *
     * return {string}
     */
    private function generateFooter(bool $html_format = true, $recover_data = false)
    {
        $hash_unsubscribe = sha1($this->random_hash);

        // TO DO que faire de cette variable ? $hash_unsubscribe pour l'instant c'est fictif est mis en place car c'est obligatoire.

        if ($html_format) {
            $opt = $recover_data ? '<a href="' . ApiMisc::getFullUrl() . '/api/action/cancel?hash_to_return=' . $recover_data . '">Annuler cette demande</a>' : '';
            return $opt . '<hr/><div align="right"><h6><strong>' . Config::OwnerMail()['footerMessage'] . '</strong> ' . date('Y') . ' <em>tous droits réserves</em><br/><a href="' . ApiMisc::getFullUrl() . '/api/mail/unsubscribe?hash_to_return=' . $hash_unsubscribe . '" >lien pour se désabonner</a ></h6></div>';
        } else {
            $opt = $recover_data ? ' Annuler cette demande: ' . ApiMisc::getFullUrl() . '/api/action/cancel?hash_to_return=' . $recover_data : '';

            return $opt . Config::OwnerMail()['footerMessage'] . date('Y') . ' tous droits réserves - ' . ApiMisc::getFullUrl() . '/api/mail/unsubscribe?hash_to_return=' . $hash_unsubscribe;
        }
    }

    /**
     * Type SETTER
     * à la suite de la reception du mail avec le code hash_to_return
     * on annule et on supprime toutes traces de la demande de changement de mot de passe.
     *
     * no return
     */
    private function cancelAction()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return], [], 1, 0);
            if (count($req)) {
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return]);
                $this->showHtml('<h1>Cette clé a été supprimé et votre demande initiale est bien annulé</h1><code>' . $hash_to_return . '</code>');
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Type SETTER
     * à la suite de la reception du mail avec le code hash_to_return
     * on changera le mot de passe par un nouveau auto generé puis nous l'enverrons par mail le nouveau mot de passe
     *
     * no return
     */
    private function changePassword()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];

            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return, 'type' => 'recover_password'], [], 1, 0);
            if (count($req)) {
                $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => intval($req[0]['id_account'])], [], 1, 0);

                $new_password = ApiMisc::passwordRandomGenerator();

                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', [
                    'password' => ApiAuthentification::__instance_singleton()->hash_password($new_password),
                ], ['id' => intval($req[0]['id_account'])], 1);

                $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                $this->mail->addAddress($account[0]['email'], $account[0]['email']);
                $this->mail->Subject = 'Nouveau mot de passe';
                $this->mail->isHTML(true);
                $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><style>html{background: #333; color: #fff;}fieldset{overflow-wrap: break-word; word-break: break-word;}textarea{margin: 0; width: 100%; resize: none;}h1{text-align: left; margin: 0;}a{color: white;}</style></head><body><div><h1>' . $account[0]['user'] . ',</h1><p> un nouveau mot de passe vient d\'etre generé avec l\'adresse IP: <strong>' . ApiMisc::remote_addr() . '</strong>. </p><h4>Nouveau mot de passe</h4><h1><code>' . $new_password . '</code></h1><p> nous te conseillons de le changer dans tes parametres à nouveau par sécurité. </p></div>' . $this->generateFooter(true) . '</body></html>';

                $this->mail->AltBody = $account[0]['user'] . ',
                    un nouveau mot de passe vient d\'etre generé avec l\'adresse IP: ' . ApiMisc::remote_addr() . '.
                    Nouveau mot de passe ' . $new_password . '
                    nous te conseillons de le changer dans tes parametres à nouveau par sécurité.
                    ' . $this->generateFooter(false) . '
                ';

                $this->addMailPreventSpammer($account[0]['email']);
                $this->mail->send();

                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return]);

                $this->showHtml('<h1>Votre nouveau mot de passe</h1><h1><code>' . $new_password . '</code></h1>');
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Type GETTER
     * Effectue une demande de changement de mot de passe, un mail avec une clé sera recu et 2 liens
     * 1/ pour demander un nouveau mot de passe
     * 2/ pour annuler la clé de demande de mot de passe
     *
     * no return
     */
    private function recoverPassword()
    {
        if (ApiToken::__instance_singleton()->isValidated() && !ApiAuthentification::__instance_singleton()->isLogged() && isset(ApiMisc::REQ_DATA()['email'])) {

            $payload = ApiToken::__instance_singleton()->getPayload();

            if (isset($payload['content']) && isset($payload['content']['captchaPassed'])) {
                if (!$payload['content']['captchaPassed']) {
                    ApiCacheData::__instance_singleton()->add_check_input(['captcha_failed']);
                    return false;
                }
            } else {
                ApiMisc::http_response_code(500);
                exit;
            }

            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['email' => ApiMisc::sanitize_string(ApiMisc::REQ_DATA()['email'])], [], 1, 0);

            if (count($req)) {
                $recover_data = array(
                    'id_account' => intval($req[0]['id']),
                    'can_expire' => 1,
                    'hash_to_return' => ApiMisc::base64UrlEncode(hash_hmac('sha256', $req[0]['user'], $this->random_hash, false)),
                    'email' => $req[0]['email'],
                    'type' => 'recover_password',
                );

                if (!$this->hasAlreadySentAnEmail($req[0]['email'])) {
                    $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'internal_mail', $recover_data);

                    if ($result) {
                        $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                        $this->mail->addAddress($req[0]['email'], $req[0]['email']);
                        $this->mail->Subject = 'Demande de nouveau mot de passe';
                        $this->mail->isHTML(true);
                        $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><style>html{background: #333; color: #fff;}fieldset{overflow-wrap: break-word; word-break: break-word;}textarea{margin: 0; width: 100%; resize: none;}h1{text-align: left; margin: 0;}a{color: white;}</style></head><body><div><h1>' . $req[0]['user'] . ',</h1><p> une demande de récupération de mot de passe vient d\'etre effectué avec l\'adresse IP: <strong>' . ApiMisc::remote_addr() . '</strong>.</p><h4>- Si il s\'agit bien de vous,</h4><p> alors cliquez sur le lien suivant pour obtenir un nouveau mot de passe.<br/><a href="' . ApiMisc::getFullUrl() . '/api/change/password?hash_to_return=' . $recover_data['hash_to_return'] . '">Changer le mot de passe</a></p></div><div><h4>- Si ce n\'est pas vous, ou bien c\'est une erreur de demande,</h4><p>vous pouvez cliquer sur le lien suivant pour effectuer une annulation.<br/></p></div>' . $this->generateFooter(true, $recover_data['hash_to_return']) . '</body></html>';

                        $this->mail->AltBody = $req[0]['user'] . ',
                        une demande de récupération de mot de passe vient d\'etre effectué avec l\'adresse IP: ' . ApiMisc::remote_addr() . '.

                        - Si il s\'agit bien de vous,
                        alors cliquez sur le lien suivant pour obtenir un nouveau mot de passe.
                        Changer le mot de passe: ' . ApiMisc::getFullUrl() . '/api/change/password?hash_to_return=' . $recover_data['hash_to_return'] . '

                        - Si ce n\'est pas vous, ou bien c\'est une erreur de demande,
                        vous pouvez cliquer sur le lien suivant pour effectuer une annulation.
                        ' . $this->generateFooter(false, $recover_data['hash_to_return']) . '
                        ';

                        $this->addMailPreventSpammer($req[0]['email']);
                        $this->mail->send();

                        ApiCacheData::__instance_singleton()->add(array('auth_response' => 'send_mailcode_password'));
                    } else {
                        ApiCacheData::__instance_singleton()->add_check_input(['email_code_already_sent']);
                    }

                } else {
                    ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);
                }

            } else {
                ApiToken::__instance_singleton()->setPayload(array(
                    'content' => [
                        'id' => -1,
                        'loggedIn' => false,
                        'rank' => 'guest',
                        'firstName' => '',
                        'lastName' => '',
                        'email' => '',
                        'captchaPassed' => false,
                    ],
                ));

                ApiCaptcha::__instance_singleton()->addCaptcha();
                ApiCacheData::__instance_singleton()->add(ApiToken::__instance_singleton()->newToken(ApiCacheData::__instance_singleton()));
                ApiCacheData::__instance_singleton()->add_check_input(['unknown_email_address']);
            }
        }
    }

    /**
     * Type GETTER
     * N'affichera que des données HTML puis mettra fin, aucune données JSON / JWT n'en sortira
     *
     * no return
     */
    private function showHtml(string $html = '')
    {
        header('Content-Type: text/html');
        echo '<style>html{background:#333;color:#fff}fieldset{overflow-wrap:break-word;word-break:break-word}textarea{margin:0;width:100%;resize:none}h1{text-align:center;margin:0;}</style>';
        echo '<fieldset><legend><strong>Récupération de données</strong></legend>' . $html . '</fieldset>';
        exit;
    }

    /**
     * Type SETTER
     * Insert ...
     * @param {$id} - ID nouvel utilisateur
     *
     * no return
     */
    public function insert(int $id)
    {
        // if (!ApiAuthentification::__instance_singleton()->isLogged() && ApiAuthentification::__instance_singleton()->getState('registered')) {
        if (!ApiAuthentification::__instance_singleton()->isLogged()) {

            $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => intval($id)], [], 1, 0);
            if (count($account)) {

                $recover_data = array(
                    'id_account' => intval($id),
                    'can_expire' => 0,
                    'hash_to_return' => ApiMisc::base64UrlEncode(hash_hmac('sha256', $account[0]['user'], $this->random_hash, false)),
                    'email' => $account[0]['email'],
                    'type' => 'account_enable',
                );

                $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'internal_mail', $recover_data);

                if ($result) {
                    // ApiCacheData::__instance_singleton()->add(array('auth_response' => 'send_mailcode_email'));

                    $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                    $this->mail->addAddress($account[0]['email'], $account[0]['email']);
                    $this->mail->Subject = 'Veuillez confirmer votre email pour activer votre compte';
                    $this->mail->isHTML(true);
                    $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><style>html{background: #333; color: #fff;}fieldset{overflow-wrap: break-word; word-break: break-word;}textarea{margin: 0; width: 100%; resize: none;}h1{text-align: left; margin: 0;}a{color: white;}</style></head><body><div><h1>' . $account[0]['user'] . ',</h1><p> pour activer votre compte vous devez valider votre email en cliquant sur le lien suivant. </p><a href="' . ApiMisc::getFullUrl() . '/api/account/enable?hash_to_return=' . $recover_data['hash_to_return'] . '" >Activer mon compte</a ></div>' . $this->generateFooter(true) . '</body></html>';

                    $this->mail->AltBody = $account[0]['user'] . ',
                        pour activer votre compte vous devez valider votre email en cliquant sur le lien suivant.

                        Activer mon compte ' . ApiMisc::getFullUrl() . '/api/account/enable?hash_to_return=' . $recover_data['hash_to_return'] . '
                        ' . $this->generateFooter(false) . '
                    ';

                    $this->addMailPreventSpammer($account[0]['email']);
                    $this->mail->send();

                    ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', ['enable' => 0], ['id' => intval($id)], 1);

                }

            } else {
                // TO DO action a faire
            }

        }
    }

    /**
     * Type SETTER
     * Update
     *
     */
    public function update(string $current_email, string $new_email)
    {
        // ApiCacheData::__instance_singleton()->add(['AAAAAAAAAAAA' . mt_rand() => 123], 0);
        if (ApiAuthentification::__instance_singleton()->isLogged()) {
            $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['email' => $current_email], [], 1, 0);
            if (count($account)) {

                $recover_data = array(
                    'id_account' => intval($account[0]['id']),
                    'can_expire' => 1,
                    'hash_to_return' => ApiMisc::base64UrlEncode(hash_hmac('sha256', $account[0]['user'], $this->random_hash, false)),
                    'email' => $new_email,
                    'type' => 'change_email',
                );

                if (!$this->hasAlreadySentAnEmail($new_email)) {
                    $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'internal_mail', $recover_data);

                    if ($result) {
                        ApiCacheData::__instance_singleton()->add(array('auth_response' => 'send_mailcode_email'));

                        $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                        $this->mail->addAddress($new_email, $new_email);
                        $this->mail->Subject = 'Changement d\'adresse email';
                        $this->mail->isHTML(true);
                        $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><style>html{background: #333; color: #fff;}fieldset{overflow-wrap: break-word; word-break: break-word;}textarea{margin: 0; width: 100%; resize: none;}h1{text-align: left; margin: 0;}a{color: white;}</style></head><body><div><h1>' . $account[0]['user'] . ',</h1><p>pour valider cette adresse email, cliquez sur le lien suivant.</p><a href="' . ApiMisc::getFullUrl() . '/api/account/confirm/mail?hash_to_return=' . $recover_data['hash_to_return'] . '" >Activer cette adresse email</a ></div>' . $this->generateFooter(true, $recover_data['hash_to_return']) . '</body></html>';

                        $this->mail->AltBody = $account[0]['user'] . ',
                        pour valider cette adresse email, cliquez sur le lien suivant.

                        Activer cette adresse email' . ApiMisc::getFullUrl() . '/api/account/confirm/mail?hash_to_return=' . $recover_data['hash_to_return'] . '
                        ' . $this->generateFooter(false, $recover_data['hash_to_return']) . '
                        ';

                        $this->addMailPreventSpammer($new_email);
                        $this->mail->send();

                    } else {
                        ApiCacheData::__instance_singleton()->add_check_input(['email_code_already_sent']);
                    }

                } else {
                    ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);
                }

            } else {
                // TO DO action a faire
            }

        }
    }

    /**
     * Type SETTER
     * Verifie la clé de validation et active le compte
     *
     * no return
     */
    private function accountEnable()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return, 'type' => 'account_enable'], [], 1, 0);
            if (count($req)) {
                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', ['enable' => 1, 'email' => $req[0]['email']], ['id' => intval($req[0]['id_account'])], 1);
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return]);

                $this->showHtml('<h1>Votre compte est désormais actif, vous pouvez vous connecter</h1>');
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Type SETTER
     * Verifie la clé de validation et valide le mail
     *
     * no return
     */
    private function confirmMail()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return, 'type' => 'change_email'], [], 1, 0);
            if (count($req)) {
                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', ['enable' => 1, 'email' => $req[0]['email']], ['id' => intval($req[0]['id_account'])], 1);
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return]);

                $this->showHtml('<h1>Votre adresse email est désormais effective</h1>');
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Type GETTER
     * Lien pour unsubscribe mail obligatoire pour tout envoi de mail
     *
     * no return
     */
    private function unsubscribeMail()
    {
        $is_sha1 = ApiMisc::is_sha1(isset(ApiMisc::REQ_DATA()['hash_to_return']) ? ApiMisc::REQ_DATA()['hash_to_return'] : '');

        if ($is_sha1) {
            $this->showHtml('<h1>Votre demande pour se désabonner des mails a été prise en compte.</h1><code>' . ApiMisc::REQ_DATA()['hash_to_return'] . '</code>');
            // TO DO
        } else {
            $this->showHtml('<h1>Aucune données n\'ont pu être traitées</h1>');
            // TO DO
        }
    }

    /**
     * Type SETTER
     * Envoi un mail pour la cloture du compte Payload.
     *
     * no return
     */
    private function askCloseAccount()
    {
        if (ApiAuthentification::__instance_singleton()->isLogged()) {
            $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => ApiMisc::getMyId()], [], 1, 0);
            if (count($account)) {

                $recover_data = array(
                    'id_account' => intval($account[0]['id']),
                    'can_expire' => 1,
                    'hash_to_return' => ApiMisc::base64UrlEncode(hash_hmac('sha256', $account[0]['user'], $this->random_hash, false)),
                    'email' => $account[0]['email'],
                    'type' => 'close_account',
                );

                if (!$this->hasAlreadySentAnEmail($account[0]['email'])) {
                    $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'internal_mail', $recover_data);

                    if ($result) {
                        ApiCacheData::__instance_singleton()->add(array('auth_response' => 'send_mail_closing_account'));

                        $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                        $this->mail->addAddress($account[0]['email'], $account[0]['email']);
                        $this->mail->Subject = 'Demande de fermeture de compte';
                        $this->mail->isHTML(true);
                        $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><style>html{background:#333;color:#fff}fieldset{overflow-wrap:break-word;word-break:break-word}textarea{margin:0;width:100%;resize:none}h1{text-align:left;margin:0}a{color:white}</style></head><body><div><h1>' . $account[0]['user'] . ',</h1><p> Une demande de cloture de compte a été effectué avec l\'adresse IP: <strong>' . ApiMisc::remote_addr() . '</strong>.</p><h4>Cette action est irreversible</h4><p> Si vous souhaitez confirmer cette action, veuillez cliquer sur le lien suivant:</p> <a href="' . ApiMisc::getFullUrl() . '/api/account/close?hash_to_return=' . $recover_data['hash_to_return'] . '" >Je confirme la fermeture définitive de mon compte</a ><p> Sinon veuillez supprimer et ignorer cet email.<br/> nb: dans les 5 minutes qui suivent ce lien ne sera plus valide.</p></div> ' . $this->generateFooter(true, $recover_data['hash_to_return']) . '</body></html>';

                        $this->mail->AltBody = $account[0]['user'] . ',
                        Une demande de cloture de compte a été effectué avec l\'adresse IP:' . ApiMisc::remote_addr() . '.

                        Cette action est irreversible

                        Si vous souhaitez confirmer cette action, veuillez cliquer sur le lien
                        suivant:
                        <a href="' . ApiMisc::getFullUrl() . '/api/account/close?hash_to_return=' . $recover_data['hash_to_return'] . '">Je confirme la fermeture définitive de mon compte</a>

                        Sinon veuillez supprimer et ignorer cet email.

                        nb: dans les 5 minutes qui suivent ce lien ne sera plus valide.
                        ' . $this->generateFooter(false, $recover_data['hash_to_return']) . '
                        ';

                        $this->addMailPreventSpammer($account[0]['email']);
                        $this->mail->send();

                    } else {
                        ApiCacheData::__instance_singleton()->add_check_input(['email_code_already_sent']);
                    }

                } else {
                    ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);
                }

            } else {
                // TO DO action a faire
            }

        }
    }

    /**
     * Type SETTER
     * Cloture le compte et le supprime.
     *
     * no return
     */
    private function closeAccount()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return, 'type' => 'close_account'], [], 1, 0);
            if (count($req)) {
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'account', ['id' => intval($req[0]['id_account'])]);

                $this->showHtml('<h1>Votre compte a bien été cloturé</h1>');
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

    /**
     * Type SETTER
     * Fais une demande pour la récuperation des données personnelles.
     *
     * no return
     */
    private function askPersonnalData()
    {
        if (ApiAuthentification::__instance_singleton()->isLogged()) {
            $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => ApiMisc::getMyId()], [], 1, 0);
            if (count($account)) {

                $recover_data = array(
                    'id_account' => intval($account[0]['id']),
                    'can_expire' => 1,
                    'hash_to_return' => ApiMisc::base64UrlEncode(hash_hmac('sha256', $account[0]['user'], $this->random_hash, false)),
                    'email' => $account[0]['email'],
                    'type' => 'recover_personnal_data',
                );

                if (!$this->hasAlreadySentAnEmail($account[0]['email'])) {
                    $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'internal_mail', $recover_data);

                    if ($result) {
                        ApiCacheData::__instance_singleton()->add(array('auth_response' => 'send_mail_personnal_data_ready'));

                        $this->mail->setFrom(Config::OwnerMail()['originMail'], $_SERVER['SERVER_NAME']);
                        $this->mail->addAddress($account[0]['email'], $account[0]['email']);
                        $this->mail->Subject = 'Vos données personnelles sont disponible';
                        $this->mail->isHTML(true);
                        $this->mail->Body = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><style>html{background:#333;color:#fff}fieldset{overflow-wrap:break-word;word-break:break-word}textarea{margin:0;width:100%;resize:none}h1{text-align:left;margin:0}a{color:white}</style></head><body><div><h1>' . $account[0]['user'] . ',</h1><p>Une demande de récupération des données a été formulé.</p><h4>Vous trouverez vos données en cliquant sur le lien suivant:</h4> <a href="' . ApiMisc::getFullUrl() . '/api/recovered/personnal/data?hash_to_return=' . $recover_data['hash_to_return'] . '" >Je récupère mes données</a ><p> <br /> nb: dans les 5 minutes qui suivent ce lien ne sera plus valide.</p></div> ' . $this->generateFooter(true) . '</body></html>';

                        $this->mail->AltBody = $account[0]['user'] . ',
                        Une demande de récupération des données a été formulé.

                        Vous trouverez vos données en cliquant sur le lien suivant:

                        ' . ApiMisc::getFullUrl() . '/api/recovered/personnal/data?hash_to_return=' . $recover_data['hash_to_return'] . '

                        nb: dans les 5 minutes qui suivent ce lien ne sera plus valide.
                        ' . $this->generateFooter(false) . '
                        ';

                        $this->addMailPreventSpammer($account[0]['email']);
                        $this->mail->send();

                    } else {
                        ApiCacheData::__instance_singleton()->add_check_input(['email_code_already_sent']);
                    }

                } else {
                    ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);
                }

            } else {
                // TO DO action a faire
            }

        }
    }

    /**
     * Type SETTER
     * Obtiens les données personnelles en lien téléchargeable.
     *
     * return {file}
     */
    private function getPersonnalData()
    {
        if (!ApiToken::__instance_singleton()->isValidated() && isset(ApiMisc::REQ_DATA()['hash_to_return'])) {
            $hash_to_return = ApiMisc::REQ_DATA()['hash_to_return'];
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return, 'type' => 'recover_personnal_data'], [], 1, 0);
            if (count($req)) {
                $sql = ApiDatabase::__instance_singleton();
                $key_id = intval($req[0]['id_account']);
                $filename = 'personnalData';

                $content = array();
                $dataCollected = array();

                // Ici je définis mes sources de collectes de données.
                $dataCollected[] = $sql->pdo_select(Config::Database()['prefix'] . 'account', ['id' => $key_id], ['id', 'user', 'email', 'created', 'last_connected', 'guid'], 1, 0, ['fetch_named']);
                $dataCollected[] = $sql->pdo_select(Config::Database()['prefix'] . 'extra_info', ['id_account' => $key_id], ['gender', 'firstName', 'lastName', 'phone', 'age', 'adress', 'citycode', 'city'], 1, 0, ['fetch_named']);

                foreach ($dataCollected as $data) {
                    $content = array_merge($content, $data);
                }

                header('Content-Type: application/octet-stream');
                header("Content-Transfer-Encoding: Binary");
                header("Content-disposition: attachment; filename=\"" . $filename . '_' . time() . '.json' . "\"");

                echo json_encode($content, JSON_PRETTY_PRINT);

                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'internal_mail', ['hash_to_return' => $hash_to_return]);

                exit;
            } else {
                $this->showHtml('<h1>Cette clé n\'existe pas ou n\'est plus valide</h1><code>' . $hash_to_return . '</code><h4>Veuillez noter que les clés sont valide 5 minutes seulement</h4>');
            }
        } else {
            http_response_code(403);
            exit;
        }
    }

}