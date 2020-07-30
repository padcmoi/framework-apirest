# API REST FRAMEWORK PHP7

### Framework API Rest pour PHP7, conçu pour être simple d’utilisation, pour un développement rapide.

C’est un projet libre, contributeurs vous êtes les bienvenues.

Permet d’être utiliser facilement sur un serveur apache2 ou nginx avec un minimum de connaissance.

compatible avec les sites web, web app / mobile app ou nimporte quel plugins AJAX ou Axios.

Projet initial https://gitlab.com/juliennaskot/api-rest-php (privé) présenté pour l'obtention d'un titre professionnel au Greta de Nice.

Le projet est une API Rest écrit avec PHP7 et fonctionnant avec une base de données MySQL.

Le point fort de ce projet est dans sa conception en framework, modifiable et libre, il a pour objectif d’être simplifié, évolutif et restera libre.

### L’objectif de ce projet est de pouvoir développer rapidement une API répondant aux exigences suivantes :

• Sécurité, nous utiliserons ce qui se fait de mieux en matière de sécurité(

> Enregistrement des mots de passe hashé selon l’algorithme de votre choix(Par défaut sha256) avec en supplément un passe de hashage afin de rendre très difficile la conversion des mots de passe stockés en base de données.
> Un jeton en 3 parties ( Header, Payload, Signature ) connu sous le nom de JsonWebToken (JWT), afin de se moderniser avec les technologies actuelles et de lutter contre les attaques CSRF ( pour Cross Site Request Forgery ), un module supplémentaire rend encore plus difficile la découverte de ce jeton par une clé supplémentaire que seul le client peut connaître lors de la première communication avec l’API.

• Gestion des données.

• Un ensemble de method static accessible dans n’importe quel Class prédéfini comme par exemple:

> REQUEST_METHOD_DATA() qui permet la récupération des données cumulé à un nettoyage automatique des données en formulaire (\*à partir de la v1.3b) désactivable en argument si besoin.

> getMyId() qui permet de retourner l’ID du jeton contenu dans le JsonWebToken du client.

• Vérification des champs de formulaire, voir (REQUEST_METHOD_DATA()).

• Conception orienté objet.

• Chaque Class est conçu en Singleton.

• Peut appeler des librairies personnalisé.

• Contient des librairies déjà préexistantes.

• Un chargeur de Class donc inutile d’utiliser un require ou include, par ailleurs elle s’instancie automatiquement si elle est conçu dans un controller avec 2 méthod requises à savoir.

> instance_modular_singleton() pour instancier la class automatiquement en Singleton.

> router() afin de recueillir les requêtes clientes de type URN pour nom uniforme de ressource ( \*voir documentation rfc.2141 ).

### Un template de base est proposé sous cette forme :

```php
class ControllerCustomExample
{
    /**
     * Pour de l'heritage multiple, plus fléxible que les namespace
     * chaque librairie peut etre stocké dans le dossier /mixins/
     */
    use CustomLibrarie1,  CustomLibrarie2,  CustomLibrarie3;

    private static $instance;
    private function __construct()
    {
        $this->pdo_db  = ApiDatbase::__instance_singleton()->pdo_useDB();
        $this->require_table();
    }
    /**
     * Instancie directement dans la Class static
     * peut etre utilisé comme un constructeur,
     * la Class ne peut s'instancier autrement que par cette method static
     * etant donné que le constructeur est en privé
     */
    public static function __instance_modular_singleton()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        self::$instance->router(); // Au moment d'instancier on charge le router
        return self::$instance;
    }
    /**
     * Type SETTER
     * Création de la table
     * no return
     */
    private function require_table()
    {
        $this->pdo_db->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "example` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }
    /**
     * Verifie si une URN est présente
     * comme identifiant de ressource en meme temps que la REQUEST_METHOD
     */
    private function router()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('show/me/example/list')) {}
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('add/me/example')) {}
                break;
            case 'PUT': // Mise à jour des données
                if (ApiMisc::isRouteUsed('change/me/example')) {}
                break;
            case 'DELETE': // Suppression de données
                if (ApiMisc::isRouteUsed('delete/me/example')) {}
                break;
        }
    }
}
```

### Comment installer (à venir ...)
