<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 * Class:           ApiDatabase
 * Description:     Class remaniée de gestion de base de données.
 *                  Permet d'utiliser des requetes SQL directement à travers des tableaux associatif en arguments.
 *                  Gère les transations.
 * Auteur:          Julien JEAN
 * Version:         v1.0.5
 * Crée le:         27/01/2020
 */
class ApiDatabase
{
    private $db, $connected = false, $current_transation = false, $lastInsertId = 0, $lastReqPDO = '';

    /**
     * Type SETTER
     * Class __instance_singleton
     * Instance la Class directement dans la class une unique fois.
     *
     * return {object} Instance
     */
    private static $singleton, $instance = null;
    private function __construct()
    {}
    public static function __instance_singleton()
    {
        if (!self::$singleton) {
            self::$instance = new self();
            self::$instance->pdo_connect();
            self::$singleton = true;
        }

        return self::$instance;
    }

    /**
     * Type SETTER
     * Essaye & Initialise la connection à la base de données
     *
     * no return
     */
    private function pdo_connect()
    {
        try {
            $this->db = new PDO(
                'mysql:host=' . Config::Database()['hostname'] .
                ';dbname=' . Config::Database()['database'] .
                ';charset=utf8;',
                Config::Database()['user'],
                Config::Database()['password']
            );
            $this->db->exec('SET NAMES utf8');
            $this->connected = true;
        } catch (PDOException $error) {
            $this->connected = false;
            ApiCacheData::__instance_singleton()->add(array('PDOException' => $error->getMessage()));
            ApiEncoders::__instance_singleton()->router();
            exit;
        }
        $this->mustBeConnected();
    }

    /**
     * Type SETTER
     * Déconnecte de la base de données en cours
     *
     * no return
     */
    private function pdo_disconnect()
    {
        $this->connected = false;
        $this->db = null;
    }

    /****************************************************************
     *                                                              *
     *  Requetes SQL
     *                                                              *
     ****************************************************************/

    /**
     * Type GETTER
     * SQL SELECT
     *
     * return {array}
     */
    public function pdo_select(string $table, array $where = [], array $selector = [], int $limit = 0, int $maxlimit = 0, array $options = [])
    {
        $this->mustBeConnected();

        $this->lastReqPDO = $sql = 'SELECT ' . self::pdo_selector($selector) . ' FROM ' . self::pdo_quote($table) . self::pdo_where_param($where) . self::pdo_limit($limit, $maxlimit);

        $req = $this->db->prepare($sql);
        $req->execute($where);
        self::fetchMode($req, $options);
        $result = $req->fetchAll(); // fetchAll pour lister tous les résultats possible, il est recommandé d'utiliser $limit
        $req->closeCursor();

        return $result;
    }

    /**
     * Type SETTER
     * SQL INSERT
     *
     * return {bool}
     */
    public function pdo_insert(string $table, array $insert = [])
    {
        $this->mustBeConnected();
        $result = false;

        $param_req = self::inverseValueToKey($insert);

        $this->lastReqPDO = $sql = 'INSERT INTO ' . self::pdo_quote($table) . ' ( ' . implode(', ', $param_req) . ' ) VALUES ( :' . implode(', :', $param_req) . ' )';

        $req = $this->db->prepare($sql);
        $result = $req->execute($insert);
        if ($result) {
            $this->lastInsertId = $this->db->lastInsertId();
        }
        $req->closeCursor();

        return $result;
    }

    /**
     * Type SETTER
     * SQL UPDATE
     *
     * return {bool}
     */
    public function pdo_update(string $table, array $set, array $where, int $limit = 0)
    {
        $this->mustBeConnected();
        $result = false;

        $data = array_merge($set, $where);

        $this->lastReqPDO = $sql = 'UPDATE ' . self::pdo_quote($table) . ' SET ' . self::pdo_set_param($set) . self::pdo_where_param($where) . self::pdo_limit($limit, 0);

        $req = $this->db->prepare($sql);
        $result = $req->execute($data);
        $req->closeCursor();

        return $result;
    }

    /**
     * Type SETTER
     * SQL DELETE
     *
     * return {bool}
     */
    public function pdo_delete(string $table, array $where, int $limit = 0)
    {
        $this->mustBeConnected();
        $result = false;

        $this->lastReqPDO = $sql = 'DELETE FROM ' . self::pdo_quote($table) . self::pdo_where_param($where) . self::pdo_limit($limit, 0);

        $req = $this->db->prepare($sql);
        $result = $req->execute($where);
        $req->closeCursor();

        return $result;
    }

    /**
     * Type SETTER
     * Permet de traiter une requete SQL personnalisé.
     * Il est préférable d'utiliser des requetes préparés afin d'eviter les injections SQL.
     *
     * return {object} - retourne une instance de PDO.
     */
    public function pdo_useDB()
    {
        $this->mustBeConnected();

        return $this->db;
    }

    /****************************************************************
     *                                                              *
     *  Transactions SQL
     *                                                              *
     ****************************************************************/
    /**
     * Type SETTER
     * Démarre la transation à condition qu'elle n'est pas commencé
     *
     * no return
     */
    public function pdo_start_transation()
    {
        $this->mustBeConnected();
        if (!$this->current_transation) {
            $this->current_transation = true;
            $this->db->beginTransaction();
        }
    }

    /**
     * Type SETTER
     * Valide toutes les requetes SQL enregistrées et met fin à
     * ApiDatabase::pdo_start_transation()
     *
     * no return
     */
    public function pdo_commit_transation()
    {
        $this->mustBeConnected();
        if ($this->current_transation) {
            $this->current_transation = false;
            $this->db->commit();
        }
    }

    /**
     * Type SETTER
     * Annule toutes les requetes SQL enregistrées
     * à l'exception des effacements et met fin à
     * ApiDatabase::pdo_start_transation()
     *
     * no return
     */
    public function pdo_rollback_transation()
    {
        $this->mustBeConnected();
        if ($this->current_transation) {
            $this->current_transation = false;
            $this->db->rollBack();
        }
    }

    /****************************************************************
     *                                                              *
     *  Parametres SQL
     *                                                              *
     ****************************************************************/

    /**
     * Type SETTER
     * Impose la connection à la base de données, sinon erreur HTTP 503 puis halt
     *
     * no return
     */
    private function mustBeConnected()
    {
        // Si pas connecté alors on tente une connection forcée à la base de données.
        if (!$this->connected) {
            $this->pdo_connect();
        }

        // Si malgrés la connection forcée, cette dernière n'est toujours pas connecté, alors on refuse.
        if (!$this->connected) {
            ApiMisc::http_response_code(503);
            exit;
        }
    }

    /**
     * Type GETTER
     * Retourne le dernier insertId contenu dans la Class PDO
     *
     * return {string}
     */
    public function pdo_lastInsertId()
    {
        return $this->lastInsertId;
    }

    /**
     * Type GETTER
     * Retourne la derniere requete SQL pour le débogage.
     *
     * return {string}
     */
    public function pdo_last_request()
    {
        return $this->lastReqPDO;
    }

    /**
     * Type GETTER
     * De base PDO echappe avec une simple quote // PDO::quote($table)
     * J'echappe avec une quote incliné, plus pratique.
     *
     * {string} $escapeString
     *
     * return {string}
     */
    public static function pdo_quote(string $escapeString)
    {
        return '`' . $escapeString . '`';
    }

    /**
     * Type GETTER
     * Inverse la clé avec la valeur dans un tableau associatif.
     *
     * return {array}
     */
    private static function inverseValueToKey(array $array)
    {
        array_walk($array, function (&$value, $key) {$value = $key;});
        return $array;
    }

    /**
     * Type GETTER
     * Si le tableau est vide, cela retournera un simple {string} *
     * Si le tableau est rempli alors il ajoutera une virgule entre chaque mot.
     *
     * return {string}
     */
    private static function pdo_selector(array $pdo_param)
    {
        return count($pdo_param) ? implode(',', $pdo_param) : '*';
    }

    /**
     * Type GETTER
     * Inverse la clé avec la valeur puis le positionne pour faire une phrase de sortie
     * pour la clause SET de UPDATE
     *
     * return {string}
     */
    private static function pdo_set_param(array $pdo_param)
    {
        $req = '';
        if (count($pdo_param)) {
            $req .= ' ';
            array_walk($pdo_param, function (&$value, $key) {$value = $key . ' = :' . $key;});
            $req .= implode(', ', $pdo_param);
        }
        return $req;
    }

    /**
     * Type GETTER
     * Inverse la clé avec la valeur puis le positionne pour faire une phrase de sortie
     * pour la clause WHERE
     *
     * return {string}
     */
    private static function pdo_where_param(array $pdo_param)
    {
        $req = '';
        if (count($pdo_param)) {
            $req .= ' WHERE ';
            array_walk($pdo_param,
                function (&$value, $key) {
                    $value = $key . ' = :' . $key;
                }
            );
            $req .= implode(' AND ', $pdo_param);
        }
        return $req;
    }

    /**
     * Type GETTER
     * Creé une phrase de sortie pour la clause LIMIT
     *
     * return {string}
     */
    private static function pdo_limit(int $page = 0, int $max = 0)
    {
        $req = '';
        if ($page > 0 || $max > 0) {
            $req .= ' LIMIT ' . $page;
            if ($max > 0) {
                $req .= ' , ' . $max;
            }
        }
        return $req;
    }

    /**
     * Type SETTER
     * Définit un FetchMode.
     *
     * no return
     */
    private static function fetchMode(Object $pdo_request, array $options)
    {
        foreach ($options as $option) {
            switch ($option) {
                case 'fetch_named':
                    $pdo_request->setFetchMode(PDO::FETCH_NAMED);
                    break;
            }
        }
    }
}