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
