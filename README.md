# PHP 8 B2B Order API

REST API de gestion de commandes B2B en PHP 8 natif, conçue comme projet de démonstration technique.

## Objectifs

- PHP 8 natif, sans framework
- API REST
- PostgreSQL
- Docker / Docker Compose
- PHPUnit
- gestion robuste du stock
- idempotence des créations de commandes
- tests unitaires et d'intégration
- préparation à un contexte réseau dégradé / synchronisation

## Démarrage

```bash
cp .env.example .env
docker compose up -d --build
```

API : http://localhost:8080/health
Documentation : http://localhost:8080/docs
Specification OpenAPI : http://localhost:8080/openapi.json
Adminer : http://localhost:8081

Dans Adminer :
- Système : PostgreSQL
- Serveur : `db`
- Utilisateur : `order_api`
- Mot de passe : `order_api`
- Base : `order_api`

## Tests

Après installation des dépendances Composer :

```bash
docker compose exec api composer install
docker compose exec api composer test
```

Les tests d'integration utilisent la base PostgreSQL du service `db` et nettoient leurs donnees de test. Le schema charge dix produits de demonstration ; le script rejouable `database/fixtures/002_demo_products.sql` permet de les ajouter a une base existante sans modifier les produits ou stocks deja presents.

Le bouton **Try it out** sur `/docs` permet d'envoyer des requetes au serveur courant, de fournir la cle idempotente, et d'inspecter le statut et la reponse sans charger de bibliotheque externe.
Le bouton **Generer** demande une cle cryptographiquement aleatoire a `GET /idempotency-key` (ou en genere une localement si l'API est inaccessible). Les commandes sont sauvegardees dans `localStorage` avant envoi ; si le reseau tombe, elles sont rejouees automatiquement au retour de la connexion avec la meme cle. Les rejets fonctionnels restent visibles dans la file et ne sont pas renvoyes en boucle.

## API disponible

- `GET /health` : disponibilite de l'API, sans connexion a la base.
- `GET /idempotency-key` : generation PHP d'une cle aleatoire pour une nouvelle commande.
- `GET /products?page=1&per_page=20` et `GET /products/{id}` : catalogue actif, prix en centimes et stock courant.
- `GET /orders?page=1&per_page=20` et `GET /orders/{id}` : commandes recentes paginees ou detail d'une commande.
- `POST /orders` : creation transactionnelle, avec `Content-Type: application/json` et une cle obligatoire `Idempotency-Key`.

Exemple :

```http
POST /orders
Content-Type: application/json
Idempotency-Key: commande-client-2026-00042

{"customer_id":42,"items":[{"product_id":1,"quantity":2}]}
```

Un rejeu avec la meme cle et le meme client/panier retourne la commande existante et ne touche pas au stock. Une cle reutilisee avec un panier different retourne `409`. Les lignes de commande conservent le prix applique au moment de l'achat. Les erreurs utilisent `{"error":"code","message":"..."}` et les reponses fournissent `X-Response-Time-ms`.

## Architecture cible

```text
Client / Frontend
       |
       v
Apache + PHP 8
       |
       +--> Controller
       |       |
       |       v
       |    Service métier
       |       |
       |       v
       |    Repository
       |       |
       v       v
    PostgreSQL
```

## Fonctionnalites implementees

La page d'accueil est disponible sur `/` et pointe vers la documentation interactive. Le routage HTTP, le catalogue, la consultation paginee des commandes, les transactions avec verrouillage des lignes produit, l'idempotence PostgreSQL, les erreurs HTTP explicites et les tests d'integration sont implementes. La documentation OpenAPI 3.1 et sa page HTML sont servies sans dependance externe.
