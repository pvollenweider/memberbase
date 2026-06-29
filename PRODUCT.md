# Product

## Register

product

## Users

Collaborateurs internes de l'organisation (1–5 personnes). Accès interne uniquement. Quatre rôles : `readonly` (consultation seule), `user` (saisie), `manager` (saisie + suppression), `admin` (gestion des comptes + accès total). La gestion des comptes est réservée aux admins.

Contexte: bureau, poste fixe ou laptop, usage régulier mais pas quotidien. Pas d'usage mobile prioritaire. L'app est un outil de travail, pas une vitrine.

## Product Purpose

Application de gestion des membres de l'organisation. Permet de créer, consulter et modifier les fiches membres, gérer l'appartenance aux équipes, suivre les cotisations et entrées comptables, générer des étiquettes d'adresse et des exports XLS. Expose une API REST JSON pour l'intégration avec des outils tiers.

Succès: un administrateur trouve et met à jour une fiche membre en moins de 30 secondes. Les exports fonctionnent sans friction. L'API retourne des données structurées sans configuration supplémentaire.

## Brand Personality

Efficace, sobre, professionnel.

Voix: direct, fonctionnel. Les labels sont clairs, les actions sont évidentes. Pas d'excès décoratif. L'outil respecte le temps de l'utilisateur.

Registre émotionnel: confiance et clarté — l'admin sait exactement où il en est.

## Anti-references

- Outils SaaS grand public (Notion, Airtable): trop de chrome, trop de marketing dans l'UI.
- Dashboards "analytics dark" avec métriques géantes: aucune pertinence ici.
- Formulaires institutionnels désorganisés (style admin PHP des années 2000): ce dont on part, pas où on va.

## Design Principles

1. **La donnée prime.** Les formulaires, tableaux et listes sont le contenu. Tout le reste est infrastructure.
2. **Densité lisible.** Un écran doit montrer beaucoup sans paraître chargé. La typographie et l'espacement font le travail.
3. **Actions évidentes.** Chaque vue a une action principale claire. Les actions secondaires recèdent.
4. **Cohérence systémique.** Même traitement pour les mêmes types d'objets (membres, équipes, entrées compta) à travers toute l'app.

## Accessibility & Inclusion

- WCAG AA minimum (4.5:1 texte courant, 3:1 grand texte et composants UI).
- Navigable au clavier pour tous les formulaires.
- Langue: français uniquement.
