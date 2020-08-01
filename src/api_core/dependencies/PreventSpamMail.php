<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           PreventSpamMail.php
 * Description:     Librairie pour prevenir le spam de l'envoi des mails de l'API
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         28/06/2020
 */
trait PreventSpamMail
{
    /**
     * Type CONSTRUCTOR
     * Verifie que les tables requises sont presentes, les ajoutes le cas échéant.
     *
     * no return
     */
    private function PreventSpamMail()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "preventspam_mail` (
            `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `id_account` int(11) NOT NULL,
            `email` varchar(80) NOT NULL,
            `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `id_account` (`id_account`),
            CONSTRAINT `account_mail_preventspam_id_account` FOREIGN KEY (`id_account`) REFERENCES `" . Config::Database()['prefix'] . "account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");

        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("DELETE FROM `" . Config::Database()['prefix'] . "preventspam_mail` WHERE TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, created)) > 60;");
    }

    /**
     * Type GETTER
     * A déjà envoyé un e-mail au cours des cinq dernières minutes.
     *
     * return {bool} retourne true si envoyé dans les 5 dernieres minutes.
     * sinon false = tout est OK
     */
    private function hasAlreadySentAnEmail(string $email)
    {
        $verify = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'preventspam_mail', ['email' => $email], [], 1, 0);

        return count($verify) ? true : false;
    }

    /**
     * Type SETTER
     * Ajoute le mail dans la base de données et empeche l'envoi d'un nouveau mail dans le délai prevu.
     *
     * return {bool} true si OK - false pour erreur
     */
    private function addMailPreventSpammer(string $email)
    {
        if ($this->hasAlreadySentAnEmail($email)) {
            return false;
        }

        $account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['email' => $email], [], 1, 0);

        if (count($account)) {
            $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'preventspam_mail', [
                'id_account' => $account[0]['id'],
                'email' => $account[0]['email'],
            ]);
            return $result ? true : false;
        } else {
            return false;
        }
    }
}