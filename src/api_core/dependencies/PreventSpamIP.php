<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           PreventSpamIP.php
 * Description:     Librairie pour prevenir le spam par IP
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         17/07/2020
 */
trait PreventSpamIP
{
    /**
     * Type CONSTRUCTOR
     * Verifie que les tables requises sont presentes, les ajoutes le cas échéant.
     *
     * no return
     */
    private function PreventSpamIP()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "preventspam_ip` (
            `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `IP` varchar(80) NOT NULL,
            `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `IP` (`IP`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");

        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("DELETE FROM `" . Config::Database()['prefix'] . "preventspam_ip` WHERE TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, created)) > " . intval(Config::PurgeAntiSpam()['SECONDS_IP']) . ";");
    }

    /**
     * Type GETTER
     * Verifie si l'IP est présenté ou non.
     *
     * return {bool} retourne true si envoyé dans les 5 dernieres minutes.
     * sinon false = tout est OK
     */
    private function isInPreventSpam()
    {
        $this->PreventSpamIP();

        $verify = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'preventspam_ip', ['IP' => ApiMisc::remote_addr()], [], 1, 0);

        return count($verify) ? true : false;
    }

    /**
     * Type SETTER
     * Ajoute le mail dans la base de données et empeche l'envoi d'un nouveau mail dans le délai prevu.
     *
     * return {bool} true si OK - false pour erreur
     */
    private function addIPForPreventSpam()
    {
        if ($this->isInPreventSpam(ApiMisc::remote_addr())) {
            return false;
        }

        $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'preventspam_ip', [
            'IP' => ApiMisc::remote_addr(),
        ]);

        return $result ? true : false;
    }
}