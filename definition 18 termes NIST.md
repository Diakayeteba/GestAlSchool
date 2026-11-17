# G. Terminologie de la taxonomie NIST appliquée au projet EduCloud

## Introduction

Dans le cadre du projet EduCloud, nous utilisons la terminologie de la taxonomie NIST (*National Institute of Standards and Technology*) afin de décrire les acteurs du cloud computing, les interactions entre les utilisateurs et les services, ainsi que les mécanismes de fourniture, de supervision et de gestion des ressources.

Notre solution repose sur une architecture en microservices conteneurisés orchestrée sous **Docker Compose** et **Docker Swarm** sur l'infrastructure de l'UPPA : **Moodle** pour la gestion de la plateforme pédagogique, **PostgreSQL** pour le stockage persistant des données, **Open WebUI** connecté à l'API externe **Google Gemini / Gemma** pour l'assistant d'intelligence artificielle RAG, **`sync-moodle`** pour l'indexation automatique des supports de cours PDF, et **`stats-api`** pour le suivi de consommation, la gestion des quotas et les abonnements en libre-service.

Les 18 notions suivantes sont analysées en adéquation directe avec l'architecture déployée et les fonctionnalités implémentées dans notre projet.

## G.1. Les acteurs du cloud computing

### 1. Consommateur de cloud (Cloud Consumer)

Le consommateur de cloud est la personne ou l'organisation qui utilise les services mis à disposition par un fournisseur.

Dans EduCloud, trois profils de consommateurs interagissent avec la plateforme :
- **Les étudiants :** Ils utilisent Moodle pour consulter les cours, interrogent l'assistant IA via Open WebUI pour réviser à partir des supports de cours, et gèrent leur niveau d'abonnement (**FREE**, **GOLD** ou **PLATINUM**) en libre-service (*On-demand self-service*) via le portail `stats-api`.
- **Les enseignants :** Ils utilisent Moodle pour structurer leurs espaces de cours et déposer des supports pédagogiques (PDF) qui alimentent automatiquement la base de connaissances de l'assistant IA.
- **L'administrateur et la direction pédagogique :** Ils supervisent l'activité de la plateforme et génèrent des bilans analytiques par IA via l'interface d'administration et le tableau de bord `stats-api`.

### 2. Fournisseur de cloud (Cloud Provider)

Le fournisseur de cloud est l'entité responsable de la mise à disposition et de la gestion des services et ressources informatiques destinés aux consommateurs.

Dans EduCloud, la fourniture s'articule sur plusieurs niveaux de responsabilité :
- **Infrastructure (IaaS) :** L'université (UPPA) fournit et héberge les machines virtuelles (`10.3.16.187` / `m1-siglis-cc-05`) ainsi que la connectivité réseau.
- **Plateforme et Applicatif (PaaS / SaaS) :** L'équipe projet agit en tant que fournisseur de services en configurant, conteneurisant et orchestrant l'écosystème applicatif (`Moodle`, `PostgreSQL`, `Open WebUI`, `sync-moodle` et `stats-api`) via Docker Compose et Docker Swarm.
- **Fournisseur tiers d'inférence IA :** L'API externe Google Gemini (`models/gemma-4-26b-a4b-it` et `gemini-embedding-001`) fournit la puissance de calcul LLM et la vectorisation RAG consommées par Open WebUI et `stats-api`.

### 3. Opérateur de réseau cloud (Cloud Carrier)

L'opérateur de réseau cloud assure l'interconnexion et le transport des données entre les consommateurs et les fournisseurs de services cloud.

Dans EduCloud, le réseau universitaire de l'UPPA (accès VPN/campus et passerelle proxy HTTP/HTTPS `cache.univ-pau.fr:3128`) ainsi que les fournisseurs d'accès à Internet assurent l'acheminement du trafic entre les navigateurs des utilisateurs et les services hébergés sur le cluster (`ports 8080, 3001, 8002`). Ce réseau garantit également les échanges sortants sécurisés vers l'API Google Gemini, tandis qu'un réseau virtuel interne Docker (`NO_PROXY`) isole et accélère les communications entre les conteneurs.

### 4. Courtier de services cloud (Cloud Broker)

Le courtier de services cloud agit comme un intermédiaire qui gère l'utilisation, les performances et la livraison de services cloud, et négocie les relations entre fournisseurs et consommateurs.

Dans EduCloud, aucun courtier de services cloud externe et indépendant n'est interposé entre l'université et les fournisseurs. En revanche, la plateforme intègre une logique d'agrégation interne : **Open WebUI** et **`stats-api`** combinent plusieurs briques techniques (authentification et cours Moodle, base PostgreSQL, moteur RAG et API LLM externe) afin d'offrir aux étudiants un point d'accès unifié et transparent.

### 5. Auditeur cloud (Cloud Auditor)

L'auditeur cloud est un tiers indépendant chargé de réaliser une évaluation objective des services cloud, notamment en matière de sécurité, de conformité et de performance.

Dans EduCloud, le périmètre auditable couvre l'isolation des secrets et clés API dans les fichiers d'environnement (`.env`), la gestion des contrôleurs d'accès dans Moodle, la disponibilité et la persistance des volumes PostgreSQL (`postgres_data`), ainsi que la résistance aux montées en charge et aux pannes de nœuds Swarm. Comme le prévoit le cadre NIST, l'évaluation académique du projet ne constitue pas un audit indépendant formel, mais les journaux d'exécution et les résultats de tests fournis dans le Cookbook constituent les preuves techniques préparatoires à un tel audit.

## G.2. Distribution et utilisation des services cloud

### 6. Distribution du cloud (Cloud Distribution)

La distribution du cloud désigne la manière dont les composants logiciels et les ressources sont répartis et exécutés sur une infrastructure distribuée.

Dans EduCloud, l'architecture est découpée en cinq services conteneurisés découplés (`moodle`, `postgres`, `open-webui`, `sync-moodle` et `stats-api`). Le déploiement s'appuie sur **Docker Compose** pour le socle applicatif et sur un cluster **Docker Swarm** (`docker-stack-stats.yml`) permettant de répartir les instances de `stats-api` sur plusieurs nœuds tout en maintenant une communication fluide via le réseau overlay/bridge Docker.

### 7. Accès au cloud (Cloud Access)

L'accès au cloud regroupe les mécanismes permettant aux utilisateurs d'atteindre et d'utiliser à distance les services hébergés.

Dans EduCloud, les consommateurs accèdent aux services exclusivement via des interfaces web standardisées (HTTP/HTTPS) depuis leur navigateur, sans jamais manipuler l'infrastructure sous-jacente : le portail pédagogique Moodle est exposé sur le port `8080`, l'assistant conversationnel Open WebUI sur le port `3001` (ou `3000`) et le tableau de bord de gestion des abonnements `stats-api` sur le port `8002` du serveur `10.3.16.187`.

### 8. Consommation de services (Service Consumption)

La consommation de services correspond à l'utilisation effective des fonctionnalités proposées par la plateforme par les consommateurs.

Dans EduCloud, la consommation se traduit par des actions métiers concrètes : consultation de cours et téléchargement de ressources sur Moodle, interrogation de l'assistant IA avec citation des sources documentaires, révision guidée via le tuteur virtuel (`/api/tuteur-interactif`), et souscription en libre-service (*On-demand self-service*) permettant de passer d'un forfait **FREE** à **GOLD** ou **PLATINUM** via un parcours de paiement simulé (avec gestion des cas de succès et de refus).

### 9. Fourniture de services (Service Provision)

La fourniture de services englobe l'ensemble des opérations techniques permettant de déployer, configurer et rendre opérationnels les services pour les consommateurs.

Dans EduCloud, cette fourniture est entièrement automatisée grâce aux descripteurs d'infrastructure (`Dockerfile`, `docker-compose.yml` et `docker-stack-stats.yml`) :
- Mise à disposition du LMS Moodle (`educloud-moodle:4.5.14`) et de sa base PostgreSQL 16.
- Déploiement du moteur RAG Open WebUI et exécution continue du worker `sync-moodle` (qui détecte toutes les 30 secondes les nouveaux fichiers PDF dans `/moodledata` et les indexe dans les bases de connaissances).
- Exposition de l'API FastAPI `stats-api` (`ple002/educloud-stats-api:v1.1`) assurant le contrôle des quotas et la supervision.

### 10. Audit de service (Service Audit)

L'audit de service consiste à contrôler le bon fonctionnement opérationnel des services et leur conformité aux règles fonctionnelles définies.

Dans EduCloud, la vérification continue s'appuie sur la sonde dédiée `GET /health` de `stats-api` (qui valide simultanément l'état de l'API et la connexion active à PostgreSQL), sur l'inspection des journaux système (`docker service logs`, `docker compose logs`) et sur les scénarios de recette documentés dans notre Cookbook (validation du rejet HTTP `403 Forbidden` en cas de dépassement de quota et vérification de l'intégrité des changements d'abonnement).

### 11. Audit des performances (Performance Audit)

L'audit des performances vise à mesurer, analyser et valider le comportement des services sous charge et en situation dégradée.

Dans EduCloud, les performances sont évaluées expérimentalement à l'aide d'**Apache JMeter** pour simuler des pics de requêtes concurrentes sur `stats-api` (`/api/stats` et `/health`), couplé à **Prometheus et Grafana** pour observer l'évolution de la latence, du débit, du CPU et de la mémoire RAM. La résilience de l'infrastructure est également auditée par la simulation de panne d'un nœud Swarm (`docker node update --availability drain`), vérifiant le redéploiement automatique (*rescheduling*) des conteneurs sans interruption de service.

### 12. Intermédiation de services (Service Intermediation)

L'intermédiation de services consiste à enrichir un service cloud existant par une couche logicielle intermédiaire apportant une valeur ajoutée fonctionnelle ou de contrôle.

Dans EduCloud, deux composants jouent ce rôle d'intermédiaire :
- **Open WebUI** s'intercale entre l'étudiant et l'API Google Gemini pour enrichir chaque requête grâce à la recherche vectorielle (RAG) sur les supports de cours synchronisés par `sync-moodle`, tout en masquant la complexité de l'API externe.
- **`stats-api`** agit comme intermédiaire de gouvernance avant l'appel au modèle IA (`/api/analyse`), en vérifiant l'identité de l'utilisateur, son niveau d'abonnement et son solde de requêtes autorisées.

## G.3. Architecture et gestion des services

### 13. Couche de service (Service Layer)

La couche de service regroupe l'ensemble des interfaces et fonctionnalités exposées aux consommateurs et aux administrateurs.

Dans EduCloud, la couche de service s'organise autour de cinq composants complémentaires :
- **Service pédagogique LMS (`moodle`, port `8080`) :** Gestion des cours, inscriptions et diffusion des supports.
- **Service de persistance des données (`postgres`, port `5432`) :** Stockage transactionnel des utilisateurs, cours et états de la plateforme.
- **Service d'assistance IA RAG (`open-webui`, port `3001`) :** Interface conversationnelle et recherche sémantique couplée à l'API Gemini.
- **Service de synchronisation documentaire (`sync-moodle`) :** Ingestion automatisée des PDF de cours vers les bases de connaissances.
- **Service de metering, quotas et analytique (`stats-api`, port `8002`) :** Exposition des endpoints REST (`/api/stats`, `/api/subscription/{user_id}`, `/api/analyse`, `/api/tuteur-interactif`) et du tableau de bord web.

### 14. Terminaux fixes / mobiles (Fixed / Mobile Endpoints)

Les terminaux fixes et mobiles désignent les dispositifs matériels utilisés par les consommateurs pour accéder aux services cloud.

Dans EduCloud, les interfaces web de Moodle (thème responsive Bootstrap), d'Open WebUI et du tableau de bord `stats-api` sont accessibles aussi bien depuis des **terminaux fixes** (postes informatiques des salles de TP de l'UPPA, ordinateurs portables personnels) que depuis des **terminaux mobiles** (smartphones et tablettes), sous réserve de disposer d'un navigateur web moderne et d'une connexion réseau.

### 15. Provisionnement rapide (Rapid Provisioning)

Le provisionnement rapide désigne la capacité à déployer, configurer et faire évoluer dynamiquement les ressources informatiques de manière automatisée.

Dans EduCloud, le déploiement complet des conteneurs est automatisé par **Docker Compose** et orchestré en cluster par **Docker Swarm** (`docker stack deploy -c docker-stack-stats.yml educloud`). Cette orchestration offre une élasticité rapide (*Rapid Elasticity*) permettant d'ajuster à chaud le nombre de réplicas d'un service lors d'un pic d'activité (`docker service scale educloud_stats-api=3`) et d'assurer la reprise automatique sur un autre nœud en cas de défaillance matérielle ou logicielle.

### 16. Supervision et rapports (Monitoring and Reporting)

La supervision et les rapports permettent de contrôler en temps réel l'état de santé de l'infrastructure, de détecter les anomalies et de restituer des indicateurs d'exploitation.

Dans EduCloud, la supervision repose sur trois mécanismes complémentaires :
- **Contrôle d'état et journaux :** L'endpoint `GET /health` de `stats-api` et les journaux Docker permettent de diagnostiquer immédiatement l'état de chaque conteneur et de la base PostgreSQL.
- **Métriques temps réel :** **Prometheus** collecte les indicateurs techniques de performance et **Grafana** les restitue sous forme de tableaux de bord visuels (utilisés notamment lors des campagnes de tests JMeter).
- **Rapports analytiques par IA :** L'endpoint `GET|POST /api/analyse` agrège les statistiques d'usage Moodle et Open WebUI pour générer automatiquement un bilan synthétique destiné à l'administration universitaire.

### 17. Mesure de l'utilisation (Metering)

La mesure de l'utilisation consiste à collecter des données quantitatives précises sur la consommation des services afin de piloter les quotas et la facturation.

Dans EduCloud, au-delà du suivi matériel des conteneurs (`docker stats` et Prometheus), le microservice **`stats-api`** implémente un **metering applicatif granulaire par utilisateur**. Grâce aux routes `GET /api/stats` et `GET /api/subscription/{user_id}`, le système interroge l'API d'Open WebUI (`/api/v1/chats/all/db`) pour comptabiliser en temps réel le nombre exact de questions posées par chaque étudiant (`questions_par_utilisateur`) et comparer cette consommation au plafond de son abonnement.

### 18. Gestion des SLA (SLA Management)

La gestion des SLA (*Service Level Agreement*) concerne la définition, l'application technique et le respect des engagements de qualité de service et de capacité entre le fournisseur et les consommateurs.

Dans EduCloud, la gestion des SLA est directement inscrite dans le code métier de `stats-api` (`LIMITES_QUOTA`) et couplée au parcours de souscription en libre-service :
- **Offre FREE (Découverte) :** Limitée à **5 questions IA** par utilisateur, avec une disponibilité *best-effort* (cible 95,0 %). Dès que le compteur atteint 5 requêtes, le système applique automatiquement le contrat en bloquant les requêtes supplémentaires via une erreur HTTP `403 Forbidden`.
- **Offre GOLD (Standard) :** Étend le quota à **50 questions IA**, débloque l'accès aux bilans analytiques (`/api/analyse`) et vise une disponibilité de 99,5 %.
- **Offre PLATINUM (Illimitée) :** Supprime tout plafond de requêtes (`limite = None`), garantit un accès prioritaire et s'appuie sur la réplication haute disponibilité de Docker Swarm (cible 99,9 %).

## Conclusion

L'application rigoureuse des 18 concepts de la taxonomie NIST permet de démontrer que l'architecture d'EduCloud couvre l'ensemble du cycle de vie d'un service cloud moderne : séparation claire des rôles entre l'infrastructure universitaire (UPPA) et la plateforme applicative orchestrée sous Docker Swarm, accélération du provisionnement, intermédiation RAG sécurisée, supervision continue via Prometheus/Grafana et application automatisée des contrats SLA grâce au metering par utilisateur de `stats-api`.