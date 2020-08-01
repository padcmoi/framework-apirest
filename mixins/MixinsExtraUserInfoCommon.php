<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

trait MixinsExtraUserInfoCommon
{

    /**
     * Type GETTER
     * Verifie les champs envoyés par l'utilisateur.
     *
     * return {array}
     */
    private function __ExtraUserInfoCommon__checkUserInput()
    {
        $REQ_DATA = ApiMisc::REQ_DATA();
        $update = array();

        if (isset($REQ_DATA['gender'])) {
            $gender = ApiMisc::sanitize_string($REQ_DATA['gender']);
            $update['gender'] = $gender === 'female' ? 'female' : 'male';
        }
        if (isset($REQ_DATA['firstName']) && strlen($REQ_DATA['firstName']) > 2 && strlen($REQ_DATA['firstName']) < 50) {
            $update['firstName'] = ApiMisc::sanitize_string($REQ_DATA['firstName']);
        }
        if (isset($REQ_DATA['lastName']) && strlen($REQ_DATA['lastName']) > 2 && strlen($REQ_DATA['lastName']) < 50) {
            $update['lastName'] = ApiMisc::sanitize_string($REQ_DATA['lastName']);
        }
        if (isset($REQ_DATA['phone']) && strlen($REQ_DATA['phone']) === 10) {
            $update['phone'] = ApiMisc::sanitize_string($REQ_DATA['phone']);
        }
        if (isset($REQ_DATA['age'])) {
            $age = ApiMisc::sanitize_string($REQ_DATA['age']);
            $update['age'] = ApiMisc::checkFormatMysqlDate($age) ? $age : '1982-01-01';
        }
        if (isset($REQ_DATA['adress']) && strlen($REQ_DATA['adress']) > 5 && strlen($REQ_DATA['adress']) < 255) {
            $update['adress'] = ApiMisc::sanitize_string($REQ_DATA['adress']);
        }
        if (isset($REQ_DATA['citycode']) && strlen($REQ_DATA['citycode']) === 5) {
            $update['citycode'] = ApiMisc::sanitize_string($REQ_DATA['citycode']);
        }
        if (isset($REQ_DATA['city']) && strlen($REQ_DATA['city']) >= 2 && strlen($REQ_DATA['city']) < 50) {
            $update['city'] = ApiMisc::sanitize_string($REQ_DATA['city']);
        }

        return $update;
    }

    /**
     * Type SETTER
     * Logout ...
     *
     * return {bool} - true pour registered / false pour erreur registered
     */
    private function __ExtraUserInfoCommon__extraInfo()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();
        $api_authentification = ApiAuthentification::__instance_singleton();

        // Pas connecté donc ne va plus loin
        if (!$this->isLogged()) {
            $api_cache_data->add(array('auth_response' => 'currently_disconnected'));
            return false;
        }

        $api_token->resetPayload();

        $api_cache_data->add($api_token->newToken($api_cache_data));

        $api_cache_data->add(array('auth_response' => 'disconnected'));

        return true;
    }

    /**
     * Type SETTER
     * Si cette méthode doit être lu, c'est que quelque chose s'est mal passé lors de la création du compte initial
     * alors on ajoute manuellement une ligne en base de données afin d'avoir un champs avec des paramètres par defaut
     * requis {array} $data - key id_account
     *
     * no return
     */
    private function __ExtraUserInfoCommon__addDefautData(array $data)
    {
        $insert = array_merge($data, $this->__ExtraUserInfoCommon__checkUserInput());
        ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'extra_info', $insert);
    }
}