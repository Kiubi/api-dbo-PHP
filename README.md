# Kiubi API Developers Back-office Client PHP


## Description

La plateforme [Kiubi](http://www.kiubi.com) fournit l'[API DBO](https://api.kiubi.com/console) aux développeurs afin d'interconnecter la plateforme avec leurs applications. Cette API leur fournis un accès direct et sécurisé aux données de tous leurs sites.

L'API DBO de Kiubi est de type REST supportant le format JSON. Afin de faciliter son utilisation, Kiubi propose une librairie PHP complète permettant la récupération des données via l'API de façon simple et optimisé. Pour en savoir plus, vous pouvez [consulter la documentation en ligne](https://aide.kiubi.com/api-dev-generalites.html).


## Console

Kiubi possède un environnement de bac à sable pour tester et débugger les appels à l'API. L'accès se fait à partir de l'adresse :

[https://api.kiubi.com/console](https://api.kiubi.com/console)

L'accès à la console est public mais l'exécution de requêtes nécessite un compte l'ouverture d'un compte sur la Plateforme. 


## Pré-requis

- Un environnement pouvant exécuter du code PHP
- PHP avec l'extension cURL (PHP >= 5.4)
- Une clé API valide

Chaque utilisateur ayant un compte Back-office a la possibilité de créer et gérer ses clés API dans son profil utilisateur afin d'utiliser l'API DBO.


## Déploiement

La librairie peut être simple déployée avec composer : 

	composer require kiubi/php-sdk
	
	
## Utilisation

Le client PHP est composé de plusieurs classes permettant de requêter l'API DBO :

- Le client :
    	
    	class Api {
    	
    	}

- La réponse :
    
    	class Response {
    	
   		}

- La gestion de l'upload de fichiers :
    
    	class File {
    	
   		}


## Méthodes disponibles

Voici la liste des méthodes disponibles dans le client PHP :

- Le client :
    - get() : Permet de lancer une requête vers l'API en méthode http GET  
    - post() : Permet de lancer une requête vers l'API en méthode http POST
    - put() : Permet de lancer une requête vers l'API en méthode http PUT
    - delete() : Permet de lancer une requête vers l'API en méthode http DELETE
    - query() : Structure une requête et lance son exécution
    - *performQuery()* : Exécute une requête vers l'API en utilisant cURL (méthode protégée)
    - *getJsonResponse()* : Retourne un object `Response` permettant de traiter la réponse à une requête (méthode protégée)
    - *prepareHeaders()* : Prépare les entêtes pour une requête multipart (méthode protégée)
    - *preparePayload()* : Prépare le contenu pour une requête multipart (méthode protégée)
    - *flattenParams()* : Transforme les paramètres multidimentionnels en paramètre simple pour une requête multipart (méthode protégée)
    - setAccessToken() : Définie la clé api utilisée pour les requêtes
    - getAccessToken() : Retourne la clé api en cours d'utilisation
    - getRateRemaining() : Retourne le quota de requête restant
    - getPage() : Permet de récupérer une page précise d'une liste de résultat
    - getNextPage() : Permet de récupérer la page suivante d'une liste de résultat  
    - getPreviousPage() : Permet de récupérer la page précédent d'une liste de résultat
    - getFirstPage() : Permet de récupérer la première page d'une liste de résultat
    - getLastPage() : Permet de récupérer la dernière page d'une liste de résultat
    - hasNextPage() : Détermine si la liste de résultat comporte une page suivante
    - hasPreviousPage() : Détermine si la liste de résultat comporte une page précédente
    - getNavigationPage() : Méthode interne qui lance les requêtes de navigation

- La réponse :
    - getHeaders() : Retourne la liste des entêtes HTTP de la réponse
    - getError() : Retourne l'erreur survenues lors de la requête
    - getMeta() : Récupère les données meta de la réponse
    - getData() : Récupère les données de la réponse
    - getHttpCode() : Retourne le code HTTP réel de la réponse
    - hasFailed() : Indique si la requête à échouée
    - hasSucceed() : Indique si la requête s'est bien déroulé

- La gestion des uploads :
    - getContentSize() : Retourne la taille du fichier en octets
    - getFilename() : Retourne le nom du fichier
    - getContentType() : Retourne le type mime du fichier
    - getContent() : Retourne le contenu du fichier

    
## Exemples

	
### Récupération de la liste de ses sites
    
	$token = '---TOKEN---';   // Votre clé API

    $api = new Kiubi\Api($token);
    $query = $api->get('sites');
    if ($query->hasFailed()) {
        $err = $query->getError();
        echo $err['message']."<br/>\n";
    }
    if ($query->hasSucceed()) {
        foreach($query->getData() as $menu) {
            echo $menu['name']."<br/>\n";
        }
    }


### Récupération des menus du Site web
    
	$token = '---TOKEN---';   // Votre clé API
	$site  = 'mon-code-site'; // Le code site

    $api = new Kiubi\Api($token);
    $query = $api->get('sites/'.$site.'/cms/menus');
    if ($query->hasFailed()) {
        $err = $query->getError();
        echo $err['message']."<br/>\n";
    }
    if ($query->hasSucceed()) {
        foreach($query->getData() as $menu) {
            echo $menu['name']."<br/>\n";
        }
    }


### Récupération des commandes d'un site

	$token = '---TOKEN---';   // Votre clé API
	$site  = 'mon-code-site'; // Le code site

    $api = new Kiubi\Api($token);
    $response = $api->get('sites/'.$site.'/checkout/orders');
    if ($response->hasSucceed()) {
        do {
            $next = false;
            $data = $response->getData();
            foreach($data as $cmd) {
                echo $cmd['creation_date'].' - '.$cmd['reference'])."<br/>\n";
            }
            if ($api->hasNextPage($response) && $api->getRateRemaining()>0) {
                $next = true;
                $response = $api->getNextPage($response);
            }   
        } while($next && $response->hasSucceed());
    }


### Envoi d'un fichier dans la médiathèque d'un site

	$token = '---TOKEN---';   // Votre clé API
	$site  = 'mon-code-site'; // Le code site

    $api = new Kiubi\Api($token);
    $response = $api->post('sites/'.$site.'/media/files', array('name'=>'fichier', 'folder_id'=>2, 'file'=>new File('/path/to/file.txt'));
	if ($response->hasSucceed()) {
        $media_id = $response->getData();
    }
	