# Exercice technique — Réservation d'inventaire

Bienvenue! Ce dépôt contient un projet Laravel + Vue 3 fonctionnel qui sert de base à un exercice technique.

**L'énoncé complet de l'exercice se trouve dans [EXERCISE.md](EXERCISE.md).**

## Prérequis

- PHP 8.4 et Composer
- Node.js 20+
- Docker (pour MySQL)

## Installation

```bash
docker compose up -d
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
composer run dev
```

L'application est ensuite accessible à [http://localhost:8000](http://localhost:8000).

MySQL écoute sur le port `3307` (pour éviter les conflits avec une instance locale). Le conteneur crée deux bases : `takehome` (développement) et `takehome_testing` (tests).

## Compte de démonstration

| Champ        | Valeur             |
| ------------ | ------------------ |
| Courriel     | `demo@example.com` |
| Mot de passe | `password`         |

## Tour du projet

- **Orders** : liste des commandes et page de détail d'une commande avec ses lignes (quantités demandées, réservées, restantes, prix). C'est à partir de cette page que la feature demandée s'intègre.
- **Warehouses** : liste des entrepôts avec un résumé de leur stock (produits en stock, unités totales).
- **Inventory** : stock physique par produit et par entrepôt. Deux tables travaillent ensemble : `inventory_balances` stocke la quantité physique courante par paire produit/entrepôt (lecture rapide), et `inventory_movements` garde l'historique complet. Les deux sont écrites dans la même transaction — **une balance doit toujours être égale à la somme de ses mouvements**. Une réservation ne modifie aucune de ces tables. L'ajustement de stock (ajout/retrait) sert d'exemple de bout en bout : contrôleur mince, FormRequest, Action, transaction, page Inertia, dialogue, tests.
- **Architecture** : la logique métier vit dans `app/Actions` (une classe = une opération, méthode `handle()`, interface `App\Actions\Action`).

## Commandes utiles

```bash
php artisan test --compact   # Tests (utilise la base takehome_testing)
composer run lint            # Formatage PHP (Pint)
composer run types:check     # Analyse statique PHP (PHPStan)
npm run lint                 # ESLint
npm run types:check          # TypeScript (vue-tsc)
npm run format               # Prettier
```

## Notes

- Les routes frontend sont générées par [Wayfinder](https://github.com/laravel/wayfinder) : après avoir ajouté des routes Laravel, elles sont régénérées automatiquement par Vite (ou via `php artisan wayfinder:generate`).
- Les tests s'exécutent contre MySQL : le conteneur Docker doit être démarré.

## Solution — Réservations d'inventaire

### Structure de données

Une table `inventory_reservations` en append-only : chaque réservation est une ligne immuable (`order_line_id`, `product_id`, `warehouse_id`, `user_id`, `quantity`). Réserver deux fois sur la même ligne/entrepôt crée deux enregistrements ; les quantités affichées sont des sommes. `product_id` est dénormalisé depuis la ligne de commande pour que le calcul du disponible (stock physique − somme des réservations du couple produit/entrepôt) soit un agrégat direct sur l'index composite `(product_id, warehouse_id)`, sans jointure sur `order_lines`. L'action `ReserveInventory` garantit l'invariant `product_id` = produit de la ligne.

Conformément à l'énoncé, une réservation ne modifie ni `inventory_balances` ni `inventory_movements`.

### Stratégie de concurrence

Verrouillage pessimiste dans une transaction (`DB::transaction`), avec deux verrous de ligne (`SELECT … FOR UPDATE`) pris dans un ordre constant pour éviter les deadlocks :

1. la ligne `order_lines` — sérialise les réservations concurrentes sur une même ligne de commande (y compris vers des entrepôts différents), protégeant la règle « quantité restante » ;
2. la ligne `inventory_balances` du couple produit/entrepôt — c'est le même verrou que celui pris par `AdjustInventory` : les réservations se sérialisent donc aussi contre les ajustements de stock, aucun des deux ne peut décider sur une lecture périmée de l'autre.

Les deux règles métier sont revalidées sous verrou, puis l'insertion a lieu dans la même transaction. `attempts: 3` rejoue la transaction en cas de deadlock détecté par MySQL. Si aucune balance n'existe pour le couple, le disponible vaut zéro et la réservation est refusée sans écriture.

Alternative écartée : une contrainte purement applicative type `INSERT … SELECT … WHERE` atomique, plus difficile à lire et incohérente avec le pattern de verrouillage déjà en place dans le projet.

### Performance

La page commande calcule les quantités réservées et disponibles en un nombre de requêtes constant (sommes groupées `SUM … GROUP BY`), quel que soit le nombre de lignes ou d'entrepôts. Index composite `(product_id, warehouse_id)` sur les réservations, plus les index des clés étrangères.

### Hypothèses

- Pas d'annulation, de libération ni de statut : une réservation existe ou n'existe pas (énoncé).
- Une commande peut être réservée quel que soit son statut.
- Tous les entrepôts sont proposés dans le dialogue ; un entrepôt sans stock du produit affiche un disponible de zéro et refuse la réservation.
- `user_id` est conservé à titre d'audit (nullable, comme sur les mouvements).

### Avec davantage de temps

- Libération / annulation de réservations, et libération automatique à l'expédition.
- Un filet de sécurité en base (colonne `reserved` dénormalisée sur `inventory_balances` avec contrainte `CHECK reserved <= quantity`), le total réservé restant recalculable depuis les réservations.
- Un test de concurrence automatisé (deux processus parallèles sur le même stock).
- Restreindre la liste du dialogue aux entrepôts ayant du stock disponible.
- Une policy d'autorisation dédiée si des rôles apparaissent.
