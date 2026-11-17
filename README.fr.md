# EduCloud — Plateforme Moodle & Assistant IA Cloud (Docker Swarm)

*[Cookbook Technique & Cahier de Recette](docs/cookbook/cookbook.md)*

Plateforme pédagogique SaaS basée sur Moodle et un assistant IA RAG (Open WebUI), orchestrée sous **Docker Swarm** avec gestion des abonnements (**On-demand self-service**), supervision de la consommation (**Measured service**) et haute disponibilité.

- **Assistant IA RAG intégré :** Les étudiants interrogent leurs supports de cours déposés sur Moodle et reçoivent des réponses sourcées.
- **Synchronisation automatique (`sync-moodle`) :** Les supports PDF déposés par les enseignants dans Moodle sont automatiquement indexés dans les bases de connaissances de l'assistant toutes les 30 secondes.
- **Portail de Metering, Quotas & SLA (`stats-api`) :** Suivi en temps réel du nombre de requêtes par utilisateur, application des quotas selon le niveau d'abonnement (**FREE**, **GOLD**, **PLATINUM**), bilan analytique IA et mise à niveau en libre-service.

---

## 1. Architecture des Services

| Service | Port publié | Port interne | Rôle dans l'architecture | Image / Origine |
| :--- | :--- | :--- | :--- | :--- |
| `moodle` | `8080` | `80` | LMS principal (cours, authentification, portail étudiant) | Build custom (`educloud-moodle:4.5.14`) |
| `postgres` | `5432` | `5432` | Base de données relationnelle persistante (`mdl_user`, `mdl_course`, abonnements) | `postgres:16` |
| `open-webui` | `3001` (ou `3000`) | `8080` | Moteur RAG et interface conversationnelle IA | `ghcr.io/open-webui/open-webui:main` |
| `sync-moodle` | Aucun | — | Worker d'ingestion automatique des PDF Moodle vers Open WebUI | Build custom (`./sync-moodle`) |
| `stats-api` | `8002` | `8000` | API FastAPI de Metering, vérification des quotas SLA (`/api/subscription`), bilan IA (`/api/analyse`) et Healthcheck (`/health`) | `ple002/educloud-stats-api:v1.1` (`./stats-api`) |

*Dépendance externe :* L'API Google Gemini / Gemma (`models/gemma-4-26b-a4b-it` et `models/gemini-embedding-001`) fournit l'inférence LLM et la vectorisation RAG via le proxy universitaire UPPA (`http://cache.univ-pau.fr:3128`).

---

## 2. Modèle de Service & Niveaux de SLA (On-Demand Self-Service)

| Niveau SLA | Quota de questions IA (`LIMITES_QUOTA`) | Fonctionnalités incluses | Disponibilité cible |
| :--- | :--- | :--- | :--- |
| **FREE** | **5 questions** | Accès de découverte au RAG et au tuteur IA (bloqué par HTTP 403 au-delà de 5 requêtes) | 95,0 % (Best-effort) |
| **GOLD** | **50 questions** | Quotas étendus, génération de bilans analytiques IA (`/api/analyse`) | 99,5 % |
| **PLATINUM** | **Illimité (`None`)** | Requêtes illimitées, priorité d'accès et rapports complets | 99,9 % |

### Endpoints principaux de `stats-api` (Port `8002`)
- `GET /` : Tableau de bord web de supervision et de gestion d'abonnement.
- `GET /health` : Contrôle d'état du service et de la connexion PostgreSQL (`200 OK` ou `503 Service Unavailable`).
- `GET /api/stats` : Comptage en temps réel des utilisateurs actifs, des cours Moodle et des questions posées par chaque étudiant dans Open WebUI.
- `GET /api/subscription/{user_id}` : Vérifie le forfait actif (`FREE`, `GOLD`, `PLATINUM`), la consommation actuelle et l'état de dépassement du quota.
- `GET|POST /api/analyse?user_id={user_id}` : Génère un rapport exécutif par IA (renvoie `403 Forbidden` si le quota du forfait est atteint).
- `GET /api/tuteur-interactif` : Tuteur virtuel en Cloud Computing.

---

## 3. Prérequis & Configuration (`.env`)

1. Copier le fichier d'exemple :
```bash
cp .env.example .env
```

2. Renseigner les clés dans `.env` :
```env
OPENAI_API_KEY=votre_cle_api_gemini
ASSISTANT_API_KEY=sk-votre_cle_api_open_webui
MOODLE_ADMIN_USER=admin
MOODLE_ADMIN_PASSWORD=votre_mot_de_passe
MOODLE_ADMIN_EMAIL=admin@educloud.local
```

---

## 4. Déploiement

### A. Déploiement sur le cluster Docker Swarm (VM UPPA)
```bash
# 1. Vérifier les nœuds du cluster Swarm
docker node ls

# 2. Charger les variables d'environnement et déployer la stack
export $(grep -v '^#' .env | xargs)
docker stack deploy -c docker-stack-stats.yml educloud

# 3. Vérifier l'état des services et des réplicas
docker stack services educloud
docker service ps educloud_stats-api
```

### B. Déploiement avec Docker Compose
```bash
docker compose up -d --build postgres moodle open-webui sync-moodle stats-api
docker compose ps
```

---

## 5. Exploitation & Commandes de vérification

```bash
# Mise à l'échelle d'un service (Rapid Elasticity)
docker service scale educloud_stats-api=3

# Vérifier l'état de santé du service (Healthcheck)
curl -i http://localhost:8002/health

# Consulter la consommation et le quota d'un utilisateur (Measured Service)
curl -s http://localhost:8002/api/subscription/demo-admin | python3 -m json.tool

# Simuler la panne d'un nœud Swarm (Fault Tolerance)
docker node update --availability drain <NOM_DU_NOEUD>
docker node update --availability active <NOM_DU_NOEUD>
```

> **Important :** Ne jamais exécuter `docker compose down -v` afin de ne pas supprimer les volumes de données (`postgres_data`, `moodle_data`, `open-webui`).

---

## 6. Déclaration d'utilisation de l'IA (Critère I)

Conformément aux règles d'intégrité académique du cours, notre équipe déclare de manière transparente l'utilisation d'outils d'IA générative (Google Gemini et ChatGPT) au cours du développement :

1. **Outils utilisés et objectifs :**
   - **Développement & Intégration (`stats-api`, `sync-moodle`) :** Aide à l'écriture du parseur de sécurité HTML (`TutorHTMLSanitizer`), des requêtes asynchrones `httpx` vers l'API d'Open WebUI (`/api/v1/chats/all/db`) et de la gestion d'erreurs HTTP (comme la résolution de l'erreur `405 Method Not Allowed` sur `/api/analyse`).
   - **Infrastructure & Réseau UPPA :** Aide à la configuration des variables `http_proxy` / `NO_PROXY` pour le proxy universitaire (`cache.univ-pau.fr:3128`) et à la rédaction des fichiers `docker-compose.yml` et `docker-stack-stats.yml`.
   - **Documentation & Modélisation :** Structuration du `README`, du Cookbook yPBL et vérification de l'alignement avec la taxonomie NIST, TOGAF et ArchiMate.

2. **Vérification, adaptation et validation critique :**
   - Chaque extrait de code généré a été testé, débogué et adapté manuellement sur les VM de l'UPPA (`10.3.16.187` / `m1-siglis-cc-05`). Par exemple, le filtrage par expression régulière des balises `<thought>...</thought>` renvoyées par le modèle `gemma-4-26b-a4b-it` et le croisement des identifiants utilisateurs entre `/api/v1/chats/all/db` et `/api/v1/users/` ont été ajustés après des tests réels sur l'API Open WebUI.
   - Les règles de quotas (`FREE=5`, `GOLD=50`, `PLATINUM=None`) et la résilience Docker Swarm ont été validées expérimentalement.