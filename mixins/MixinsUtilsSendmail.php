<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Trait MixinsUtilsSendmail
 * Utilitaire Captcha
 */
trait MixinsUtilsSendmail
{
    use PreventSpamIP;

    private $mail;

    /**
     * Type GETTER
     * Généré un pied de page
     *
     * return {string}
     */
    private function generateFooter(bool $html_format = true)
    {
        $hash_unsubscribe = sha1(time() . mt_rand());

        if ($html_format) {
            $msg = '<hr/><div align="right"><h6><strong>' . Config::OwnerMail()['footerMessage'] . '</strong> ' . date('Y') . ' <em>tous droits réserves</em><br/><a href="' . ApiMisc::getFullUrl() . '/api/mail/unsubscribe?hash_to_return=' . $hash_unsubscribe . '" >lien pour se désabonner</a ></h6></div>';
        } else {
            $msg = Config::OwnerMail()['footerMessage'] . date('Y') . ' tous droits réserves - ' . ApiMisc::getFullUrl() . '/api/mail/unsubscribe?hash_to_return=' . $hash_unsubscribe;
        }

        return $msg;
    }

    private function _MUS_init()
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

    private function writeMailMessage(string $senderEmail, string $objEmail, string $htmlEmail, string $textEmail)
    {
        // if (!$this->hasAlreadySentAnEmail($senderEmail)) {
        if (!$this->isInPreventSpam()) {
            $this->_MUS_init();

            $this->mail->setFrom('noreply@naskot.fr', $_SERVER['SERVER_NAME']);
            $this->mail->addAddress(Config::OwnerMail()['destEmail'], Config::OwnerMail()['destEmail']);
            $this->mail->Subject = $objEmail;
            $this->mail->isHTML(true);
            $this->mail->Body = $htmlEmail;

            $this->mail->AltBody = $textEmail;

            $this->mail->send();

            // !!! DO !!! mettre en place une lutte contre le spam par IP car les mails requierent l'ID d un compte et
            //ces envois sont anonymes de plus rien ne prouvent que l'adresse mail donné est réelle
            $this->addIPForPreventSpam();

            ApiCacheData::__instance_singleton()->add([
                'mail_successfull_send' => '1',
            ]);

        } else {
            ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);

        }

    }

}