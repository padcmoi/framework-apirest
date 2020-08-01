<?php
/**
 *   API REST Php
 *   Version 0.0.1a
 *   Contribution: Julien JEAN
 *
 *
 *    MIT License
 *
 *    Copyright (c) 2020 Julien JEAN
 *
 *    Permission is hereby granted, free of charge, to any person obtaining a copy
 *    of this software and associated documentation files (the "Software"), to deal
 *    in the Software without restriction, including without limitation the rights
 *    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *    copies of the Software, and to permit persons to whom the Software is
 *    furnished to do so, subject to the following conditions:
 *
 *    The above copyright notice and this permission notice shall be included in all
 *    copies or substantial portions of the Software.
 *
 *    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *    SOFTWARE.
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
$api_version = '1.0a';
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
    'api_core/Config.php',
    'api_core/modules/ApiCaptcha.php',
    'api_core/modules/ApiInternalMail.php',
    'api_core/modules/PHPMailer_Main.php',
    'api_core/modules/PHPMailer_SMTP.php',
    'api_core/modules/PreventSpamIP.php',
    'api_core/modules/PreventSpamMail.php',
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