# Cookbook Technique — Projet EduCloud (M1 SIGLIS)

**Équipe :** Phuong (Dev App), Mohammed (DevOps & Swarm), Fatou (Business & NIST), Madou (Architecture & Documentation)  
**Environnement cible :** Cluster de machines virtuelles UPPA (`10.3.16.187` / `m1-siglis-cc-05`)  
**Orchestrateur :** Docker Swarm & Docker Compose

---

## 1. Préparation de l'environnement et Prérequis

### 1.1 Configuration du Proxy Universitaire (UPPA)
Sur les machines virtuelles de l'université, l'accès sortant vers Internet (Docker Hub, GitHub, API Google Gemini) passe par le proxy `http://cache.univ-pau.fr:3128`, tandis que les flux inter-conteneurs doivent l'ignorer :

```bash
export http_proxy="http://cache.univ-pau.fr:3128"
export https_proxy="http://cache.univ-pau.fr:3128"
export NO_PROXY="localhost,127.0.0.1,10.3.16.187,postgres,open-webui,moodle,stats-api"
```

### 1.2 Récupération du dépôt et configuration des secrets (`.env`)

```bash
git clone https://github.com/phuonglnt/Edu-cloud-DEMO.git
cd Edu-cloud-DEMO
cp .env.example .env
```

Contenu attendu dans `.env` :

```env
OPENAI_API_KEY=AIzaSy...                 # Clé API Google AI Studio (Gemini/Gemma)
ASSISTANT_API_KEY=sk-...                 # Clé API générée dans Open WebUI
MOODLE_ADMIN_USER=admin
MOODLE_ADMIN_PASSWORD=AdminPassword123!
MOODLE_ADMIN_EMAIL=admin@educloud.local
```

---

## 2. Procédure de Déploiement

### 2.1 Démarrage du socle applicatif (Moodle, PostgreSQL, Open WebUI, Sync-Moodle)

```bash
docker compose up -d --build postgres moodle open-webui sync-moodle
docker compose ps
```

### 2.2 Initialisation du cluster Docker Swarm et déploiement de `stats-api`
Sur le nœud Manager (`10.3.16.187` / `m1-siglis-cc-05`) :

```bash
# 1. Initialiser le Swarm (si non actif) et vérifier les nœuds
docker swarm init --advertise-addr 10.3.16.187
docker node ls

# 2. Exporter les variables d'environnement et déployer la stack Swarm
export $(grep -v '^#' .env | xargs)
docker stack deploy -c docker-stack-stats.yml educloud

# 3. Vérifier l'état du déploiement
docker stack services educloud
docker service ps educloud_stats-api
```

> 📸 **[INSÉRER CAPTURE 1 (MOHAMMED) : Sortie de `docker node ls` et `docker stack services educloud` montrant les services actifs]**

---

## 3. Cahier de Recette — Les 4 Cas d'Utilisation (Démonstration Soutenance)

### Cas d'utilisation 1 : On-Demand Self-Service & Simulation de Paiement (FREE $\rightarrow$ GOLD)

* **Objectif :** Démontrer qu'un étudiant peut souscrire ou mettre à niveau son abonnement en libre-service sans intervention d'un administrateur, avec gestion d'un paiement accepté et d'un paiement refusé.
* **Situation initiale :** L'utilisateur `demo-admin` (ou un étudiant) est inscrit sur le niveau par défaut **FREE** (limité à 5 questions IA).
* **Actions exécutées :**
  1. Vérification de l'abonnement initial via l'API ou le tableau de bord (`http://10.3.16.187:8002`) :
     ```bash
     curl -s http://localhost:8002/api/subscription/demo-admin | python3 -m json.tool
     ```
  2. **Scénario A — Échec de paiement :** L'utilisateur sélectionne l'offre **GOLD** et soumet un moyen de paiement invalide/refusé sur l'interface de souscription.
  3. **Scénario B — Succès du paiement :** L'utilisateur valide le paiement pour l'offre **GOLD** (50 questions) ou **PLATINUM** (illimité).
* **Comportement attendu :**
  * En cas d'échec de paiement : retour d'erreur, l'abonnement reste bloqué sur `FREE` (limite = `5`).
  * En cas de succès : mise à jour immédiate du profil à `GOLD` (limite = `50`) sans redémarrage de service.
* **Résultat observé & Preuves :**
  * Réponse JSON de `/api/subscription/demo-admin` confirmant `"tier": "GOLD"` et `"limite": 50`.

> 📸 **[INSÉRER CAPTURE 2A (PHUONG) : Interface de paiement simulé affichant un paiement refusé + maintien en FREE]**  
> 📸 **[INSÉRER CAPTURE 2B (PHUONG) : Interface affichant le paiement validé + passage effectif en GOLD]**

---

### Cas d'utilisation 2 : Measured Service & Blocage des Quotas par SLA

* **Objectif :** Démontrer le comptage précis de la consommation par utilisateur et le blocage automatique dès que le plafond du contrat SLA est atteint.
* **Situation initiale :** L'utilisateur dispose d'un forfait **FREE** (`LIMITES_QUOTA["FREE"] = 5`) et a déjà posé 5 questions à l'assistant IA dans Open WebUI.
* **Actions exécutées :**
  1. Interrogation des métriques globales et par utilisateur :
     ```bash
     curl -s http://localhost:8002/api/stats | python3 -m json.tool
     ```
  2. Vérification de l'état de dépassement du quota :
     ```bash
     curl -s http://localhost:8002/api/subscription/demo-admin | python3 -m json.tool
     ```
  3. Tentative d'exécution d'une nouvelle analyse IA au-delà du quota autorisé :
     ```bash
     curl -i "http://localhost:8002/api/analyse?user_id=demo-admin"
     ```
* **Comportement attendu :** Le service `stats-api` détecte que `nb_questions >= 5`, refuse l'appel au LLM et renvoie un code **HTTP `403 Forbidden`** invitant l'utilisateur à passer au forfait supérieur.
* **Résultat observé & Preuves :**
  ```json
  HTTP/1.1 403 Forbidden
  {"detail": "Quota FREE atteint (5/5 questions). Veuillez passer au niveau supérieur."}
  ```

> 📸 **[INSÉRER CAPTURE 3 (PHUONG) : Message de blocage HTTP 403 / Alerte de quota dépassé sur le tableau de bord]**

---

### Cas d'utilisation 3 : Rapid Elasticity & Tests de Charge (Apache JMeter & Monitoring)

* **Objectif :** Démontrer la capacité de la plateforme à absorber un pic de charge par mise à l'échelle horizontale (réplicas Docker Swarm) et visualiser les métriques sous Prometheus/Grafana.
* **Situation initiale :** Le service `educloud_stats-api` tourne avec `1` seul réplica actif.
* **Actions exécutées :**
  1. Lancement d'un plan de test **Apache JMeter** envoyant un flux massif de requêtes concurrentes sur `http://10.3.16.187:8002/api/stats` et `/health`.
  2. Observation de la montée en charge (CPU / temps de réponse) sur Prometheus / Grafana.
  3. Mise à l'échelle horizontale du service sur le cluster Swarm :
     ```bash
     docker service scale educloud_stats-api=3
     docker service ps educloud_stats-api
     ```
  4. Relance du tir JMeter pour comparer les temps de réponse avec 3 réplicas.
* **Comportement attendu :** Répartition de la charge entre les 3 instances du conteneur `stats-api` et diminution du temps de réponse moyen sous forte concurrence.
* **Résultat observé & Preuves :**

> 📸 **[INSÉRER CAPTURE 4A (MOHAMMED) : Résultats Apache JMeter (temps de réponse / débit) et sortie de `docker service scale`]**  
> 📸 **[INSÉRER CAPTURE 4B (MOHAMMED) : Dashboard Grafana / Prometheus montrant le pic de charge]**

---

### Cas d'utilisation 4 : Tolérance aux Pannes & Haute Disponibilité (Fault Tolerance Swarm)

* **Objectif :** Prouver que la perte ou la mise en maintenance d'un nœud du cluster Docker Swarm n'interrompt pas le service pour les étudiants.
* **Situation initiale :** Le cluster Swarm est actif avec ses nœuds opérationnels (`Status: Ready`, `Availability: Active`) et les réplicas de `educloud_stats-api` distribués.
* **Actions exécutées :**
  1. Vérification de l'état de santé initial :
     ```bash
     curl -i http://localhost:8002/health
     docker service ps educloud_stats-api
     ```
  2. Simulation d'une panne franche (ou mise en mode `drain`) d'un nœud exécutant le service :
     ```bash
     docker node update --availability drain <NOM_NOEUD_WORKER>
     ```
  3. Observation de la reprogrammation automatique (*rescheduling*) des conteneurs par le Swarm Manager :
     ```bash
     docker service ps educloud_stats-api
     curl -i http://localhost:8002/health
     ```
  4. Réactivation du nœud après test :
     ```bash
     docker node update --availability active <NOM_NOEUD_WORKER>
     ```
* **Comportement attendu :** Docker Swarm détecte l'indisponibilité du nœud, arrête les tâches sur le nœud défaillant et recrée automatiquement les réplicas sur un nœud actif en quelques secondes. L'endpoint `/health` continue de répondre `{"status": "en ligne", "base_de_donnees": "connectée"}` (HTTP 200).
* **Résultat observé & Preuves :**

> 📸 **[INSÉRER CAPTURE 5 (MOHAMMED) : Sortie de `docker node ls` (nœud en Drain/Down) + `docker service ps educloud_stats-api` montrant le redéploiement automatique sur un autre nœud]**

---

## 4. Procédure d'Arrêt et de Maintenance

```bash
# Retirer uniquement la stack Swarm stats-api
docker stack rm educloud

# Arrêter les conteneurs Compose en conservant les volumes de données
docker compose stop
```

> *(Ne jamais utiliser `docker compose down -v` afin de préserver les données PostgreSQL et les bases vectorielles Open WebUI).*