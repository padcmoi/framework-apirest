<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

trait MixinsExtraUserInfoGet
{

    /**
     * Type SETTER
     * Select ...
     *
     * no return
     */
    private function get()
    {
        $payload = ApiToken::__instance_singleton()->getPayload();

        if (ApiAuthentification::__instance_singleton()->isLogged() && isset($payload['content']) && isset($payload['content']['id'])) {

            $key_id = $payload['content']['id'];

            $where_clause = array(
                'id_account' => $key_id,
            );
            $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'extra_info', $where_clause, [], 1, 0);

            if (count($req)) {
                $this->extra_user_info = array(
                    'gender' => ApiMisc::sanitize_string($req[0]['gender']),
                    'firstName' => ApiMisc::sanitize_string($req[0]['firstName']),
                    'lastName' => ApiMisc::sanitize_string($req[0]['lastName']),
                    'phone' => ApiMisc::sanitize_string($req[0]['phone']),
                    'age' => ApiMisc::sanitize_string($req[0]['age']),
                    'adress' => ApiMisc::sanitize_string($req[0]['adress']),
                    'citycode' => ApiMisc::sanitize_string($req[0]['citycode']),
                    'city' => ApiMisc::sanitize_string($req[0]['city']),
                );
            } else {
                $this->extra_user_info = array(
                    'gender' => 'male',
                    'firstName' => '',
                    'lastName' => '',
                    'phone' => '',
                    'age' => '1982-01-01',
                    'adress' => '',
                    'citycode' => '00000',
                    'city' => '',
                );

                $this->__ExtraUserInfoCommon__addDefautData($where_clause);
            }

            ApiCacheData::__instance_singleton()->add(array(
                'extra_user_info' => $this->extra_user_info,
            ));

        }
    }
}