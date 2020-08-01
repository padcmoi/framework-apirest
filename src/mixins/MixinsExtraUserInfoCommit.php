<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

trait MixinsExtraUserInfoCommit
{
    /**
     * Type SETTER
     * Insert ...
     *
     * no return
     */
    private function insert()
    {
        if (!ApiAuthentification::__instance_singleton()->isLogged() && ApiAuthentification::__instance_singleton()->getState('registered')) {

            $insert = array('id_account' => intval(ApiAuthentification::__instance_singleton()->getState('registered')));
            $checkinput = $this->__ExtraUserInfoCommon__checkUserInput();
            $insert = array_merge($insert, $checkinput);

            if (!count(ApiCacheData::__instance_singleton()->get_check_input()) && count($checkinput) === 8) {
                $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'extra_info', $insert);
                if ($result) {
                    ApiCacheData::__instance_singleton()->add(array('auth_response' => 'account_created'));
                    ApiCacheData::__instance_singleton()->add(array('extra_user_info' => $this->extra_user_info));
                    ApiDatabase::__instance_singleton()->pdo_commit_transation();
                } else {
                    ApiCacheData::__instance_singleton()->add(array('auth_response' => 'account_failed2'));
                    ApiCacheData::__instance_singleton()->add_check_input(['something_is_wrong']);
                    ApiDatabase::__instance_singleton()->pdo_rollback_transation();
                    ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'account', ['id' => intval(ApiAuthentification::__instance_singleton()->getState('registered'))]);
                }
            } else {
                ApiCacheData::__instance_singleton()->add(array('auth_response' => 'account_failed3'));
                ApiCacheData::__instance_singleton()->add_check_input(['something_is_wrong']);
                ApiDatabase::__instance_singleton()->pdo_rollback_transation();
                ApiDatabase::__instance_singleton()->pdo_delete(Config::Database()['prefix'] . 'account', ['id' => intval(ApiAuthentification::__instance_singleton()->getState('registered'))]);
            }
        }
    }

    /**
     * Type SETTER
     * Change ...
     *
     * no return
     */
    private function change()
    {
        if (ApiAuthentification::__instance_singleton()->isLogged() && ApiAuthentification::__instance_singleton()->getState('changed')) {
            $payload = ApiToken::__instance_singleton()->getPayload();
            if (isset($payload['content']) && isset($payload['content']['id'])) {

                $key_id = $payload['content']['id'];
                $update = $this->__ExtraUserInfoCommon__checkUserInput();

                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'extra_info', $update, ['id_account' => $key_id], 1);
            }
        }

    }

    /**
     * Type SETTER
     * Logout ...
     *
     * no return
     */
    private function logout()
    {
        if (ApiAuthentification::__instance_singleton()->getState('logout')) {
            $this->extra_user_info = array(
                'gender' => 'male',
                'firstName' => '',
                'lastName' => '',
                'phone' => '',
                'age' => '1970-01-01',
                'adress' => '',
                'citycode' => '00000',
                'city' => '',
            );

            ApiCacheData::__instance_singleton()->add(array(
                'extra_user_info' => $this->extra_user_info,
            ));
        }
    }
}