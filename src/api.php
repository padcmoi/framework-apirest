<?php
/**
 *   API REST Php
 *   Créateur: Julien JEAN
 *
 *
 *    Licence Creative Commons BY-SA 3.0 FR
 *    La licence est présente à la racine du projet
 *    Copyright (c) 2020 Julien JEAN
 *
 *
 */

/****************************************************************
 *                                                              *
 *  1.Protection contre l'inclusion non autorisé
 *                                                              *
 ****************************************************************/

$security_include_identifier = crc32(mt_rand()); // Empêche l'utilisation des autres fichiers php sans le chargement de cette variable.

/****************************************************************
 *                                                              *
 *  2.Paramètres
 *                                                              *
 ****************************************************************/
require_once 'config.env.php';
$api_version = '1.3b';
$time_app_exec = microtime(true); // debut du microtime pour <result_generated_in>
date_default_timezone_set('Europe/Paris'); // Timezone Paris

/****************************************************************
 *                                                              *
 *  3.Definition automatique des mises à jours
 *                                                              *
 ****************************************************************/

$files_to_watch = array(
    'api.php',
    'api_core/ApiAuthentification.php',
    'api_core/ApiCacheData.php',
    'api_core/ApiControllers.php',
    'api_core/ApiDatabase.php',
    'api_core/ApiEncoders.php',
    'api_core/ApiManager.php',
    'api_core/ApiMisc.php',
    'api_core/ApiSessionActive.php',
    'api_core/ApiToken.php',
    'api_core/ApiCaptcha.php',
    'api_core/ApiInternalMail.php',
    'api_core/dependencies/PHPMailer_Main.php',
    'api_core/dependencies/PHPMailer_SMTP.php',
    'api_core/dependencies/PreventSpamIP.php',
    'api_core/dependencies/PreventSpamMail.php',
    'views/Html.php',
    'views/Json.php',
    'views/Xml.php',
);
$api_last_update = 1089805798; // La date de modification du fichier ApiManager determine la derniere mise à jour de l'API
$api_last_update = array(
    'time' => 0,
    'file' => 'n/a',
);
foreach ($files_to_watch as $file_to_watch) {
    if (file_exists($file_to_watch)) {
        if (filemtime($file_to_watch) > $api_last_update['time']) {
            $api_last_update['time'] = filemtime($file_to_watch);
            $api_last_update['file'] = $file_to_watch;
        }
    }
}

/****************************************************************
 *                                                              *
 *  4.Chargement de l'API Core ApiManager
 *    qui va gerer tous les modules
 *                                                              *
 ****************************************************************/

require 'api_core/ApiManager.php';