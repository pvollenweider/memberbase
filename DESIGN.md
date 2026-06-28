---
name: Casa Membres
description: Outil de gestion des membres de l'organisation — admin interne sobre et efficace.
register: product
colors:
  primary: "#4f7ac7"
  primary-dark: "#3a5fa0"
  primary-light: "#e8eef8"
  ink: "#1f2937"
  ink-mid: "#374151"
  ink-muted: "#6b7280"
  surface: "#ffffff"
  ground: "#f9fafb"
  border: "#e5e7eb"
  border-strong: "#d1d5db"
  danger: "#dc2626"
  danger-light: "#fee2e2"
  success: "#16a34a"
  success-light: "#dcfce7"
  warning: "#d97706"
  warning-light: "#fef3c7"
  male: "#3b82f6"
  female: "#f472b6"
typography:
  base:
    fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    color: "#1f2937"
  heading:
    fontFamily: "Inter, system-ui, sans-serif"
    fontWeight: 600
    lineHeight: 1.3
  label:
    fontSize: "0.75rem"
    fontWeight: 500
    letterSpacing: "0.025em"
    textTransform: "uppercase"
    color: "#6b7280"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace"
    fontSize: "0.875em"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  "2xl": "48px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-primary-hover:
    backgroundColor: "{colors.primary-dark}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-mid}"
    border: "1px solid {colors.border-strong}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  badge-primary:
    backgroundColor: "{colors.primary-light}"
    textColor: "{colors.primary-dark}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
  badge-success:
    backgroundColor: "{colors.success-light}"
    textColor: "{colors.success}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
  badge-danger:
    backgroundColor: "{colors.danger-light}"
    textColor: "{colors.danger}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
  card:
    backgroundColor: "{colors.surface}"
    border: "1px solid {colors.border}"
    rounded: "{rounded.lg}"
    padding: "20px 24px"
  navbar:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    height: "56px"
  table-row-hover:
    backgroundColor: "{colors.primary-light}"
  input:
    border: "1px solid {colors.border-strong}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    focus-border: "{colors.primary}"
---

# Design System: Casa Membres

## 1. Vue d'ensemble

**Direction: "Admin propre"**

Interface de gestion interne sobre, dense et efficace. Pas de marketing, pas de décoration. L'utilisateur est un administrateur qui connaît l'outil — l'interface doit respecter son temps et ne jamais le ralentir.

Stack cible: **Bootstrap 5** + Inter + DataTables. Remplacement du Bootstrap 4 actuel. Migration PHP 8.2 / Debian Bookworm.

## 2. Couleurs

Palette fonctionnelle à 3 couches: fond, surfaces, signaux.

- **Primary** (`#4f7ac7`): Actions principales, navbar, liens actifs. Légèrement plus saturé que le bleu actuel (#6088c1) pour plus de contraste.
- **Primary Dark** (`#3a5fa0`): Hover, états actifs.
- **Primary Light** (`#e8eef8`): Backgrounds de badges, hover de lignes tableau.
- **Ink** (`#1f2937`): Texte principal. 14:1 sur blanc.
- **Ink Mid** (`#374151`): Labels, secondaire.
- **Ink Muted** (`#6b7280`): Métadonnées, texte désactivé. Vérifier 4.5:1 selon le fond.
- **Ground** (`#f9fafb`): Fond de page. Neutre pur, pas teinté.
- **Surface** (`#ffffff`): Cards, formulaires, tableau.
- **Border** (`#e5e7eb`) / **Border Strong** (`#d1d5db`): Séparateurs et contours.
- **Danger/Success/Warning**: signaux système uniquement — pas de décoration.

### Couleurs sémantiques membres
- **Male** (`#3b82f6`): icône genre masculin.
- **Female** (`#f472b6`): icône genre féminin.

## 3. Typographie

**Inter** remplace Helvetica Neue. Plus lisible à 14px, meilleur support des chiffres tabulaires pour la compta.

- **Base**: 14px, 400, lh 1.5. Tous les contenus courants.
- **Heading**: Semi-bold 600. Titres de section, titres de card.
- **Label**: 12px, 500, uppercase, tracked 0.025em, muted. Libellés de champs, en-têtes de colonnes.
- **Mono**: Pour IDs, timestamps, valeurs numériques précises.

## 4. Composants

### Navbar
Fond `primary` (#4f7ac7). Texte blanc. Hauteur 56px. Logo "MemberBase — Membres" à gauche. Nav items principaux (Membres, Équipes, Compta, Export) à droite. Actif: bold + underline blanc.

### Tableau membres (DataTable)
- En-têtes: fond `ground`, label style (uppercase, muted, 12px).
- Lignes: alternance surface/surface (pas de zebra-striping harsh). Hover: `primary-light`.
- Actions par ligne: icônes Font Awesome, couleur `ink-muted`, hover `primary`.
- Pagination: style Bootstrap 5 standard.

### Formulaires (fiche membre)
- Layout 2 colonnes sur ≥768px, 1 colonne mobile.
- Labels au-dessus des champs (pas en ligne). Plus lisible, meilleur mobile.
- Champs: bordure `border-strong`, radius `md`, focus ring `primary`.
- Sections: card avec titre h5 semi-bold. Ex: "Coordonnées", "Appartenance aux équipes", "Cotisations".

### Badges statut
- Cotisation payée: badge `success`.
- Cotisation impayée: badge `danger`.
- Équipe: badge `primary`.

### Cards
Bordure légère `border`, radius `lg`, padding `20px 24px`. Pas de shadow.

## 5. À faire (upgrade Bootstrap 4 → 5)

- Remplacer `data-toggle` → `data-bs-toggle`, `data-target` → `data-bs-target`
- `mr-*`/`ml-*` → `me-*`/`ms-*`, `float-left`/`float-right` → `float-start`/`float-end`
- `form-group` supprimé → `mb-3`
- Recharger jQuery: Bootstrap 5 ne dépend plus de jQuery (mais DataTables en a besoin — garder)
- Mettre à jour DataTables → v2.x (compatible BS5)
- Mettre à jour la CSS custom.css pour les nouvelles variables Bootstrap 5

## 6. Do's and Don'ts

### Do:
- Labels uppercase 12px pour les en-têtes de colonnes et libellés de champs.
- Inter à 14px pour tout le contenu courant.
- Garder DataTables — c'est le composant le plus utilisé.
- Actions principales toujours en `button-primary`. Destructives en `button-danger`.
- Fond `ground` pour la page, `surface` pour les cards et formulaires.

### Don't:
- Pas de sidebar: layout sans navigation latérale. La navbar horizontale suffit.
- Pas de dashboard avec métriques géantes. C'est un outil de saisie, pas un tableau de bord analytique.
- Pas de Modal pour tout: les formulaires d'édition lourds méritent leur propre page.
- Pas d'animations: outil d'admin, pas d'application grand public.
- Pas de fond coloré sur la page entière: `ground` (#f9fafb) neutre uniquement.
