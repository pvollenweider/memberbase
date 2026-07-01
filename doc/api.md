# Référence API MemberBase

API REST interne de l'application MemberBase. Toutes les réponses sont en JSON UTF-8.

---

## Authentification

L'API ne dispose pas de token séparé. Elle repose sur **la session web de l'application** : la requête doit porter le cookie de session obtenu lors d'une connexion via l'interface web.

Si la session est absente ou expirée, toutes les routes retournent `401`.

**Rôles requis** (depuis la v3.5.4) : les lectures (`GET`) exigent le rôle minimal
`canRead()` (`readonly` et au-dessus) ; les écritures (`POST`/`PUT`/`DELETE`)
exigent `canWrite()` (`user` et au-dessus). Un rôle insuffisant retourne `403`.

> **Terminologie.** L'interface parle désormais de **Segment** ; l'API conserve
> les chemins `/api/groups` et le vocabulaire « groupe » d'origine (entité `team`).

```bash
# Exemple : login via le formulaire web, puis réutiliser le cookie
curl -c cookies.txt -b cookies.txt \
  -X POST https://votre-domaine/login \
  -d "username=alice&password=secret"

# Toutes les requêtes API suivantes portent ce cookie
curl -b cookies.txt https://votre-domaine/api/members
```

---

## Format des réponses

- **Content-Type** : `application/json; charset=utf-8`
- **Envelope succès** : `{ "data": … }` (objet ou tableau)
- **Envelope liste** : `{ "data": […], "meta": { "page": 1, "limit": 25, "total": 142 } }`
- **Erreur** : `{ "error": "message lisible" }`

### Codes HTTP utilisés

| Code | Signification |
|------|---------------|
| `200` | Succès (GET, PATCH/PUT) |
| `201` | Ressource créée (POST) |
| `204` | Succès sans corps (DELETE) |
| `400` | Paramètre manquant ou invalide |
| `401` | Non authentifié (session absente ou expirée) |
| `403` | Rôle insuffisant |
| `404` | Ressource introuvable |
| `405` | Méthode non autorisée |
| `409` | Conflit (ex. groupe non vide) |
| `422` | Données manquantes ou invalides dans le corps |
| `500` | Erreur serveur interne |

---

## Membres

### `GET /api/members`

Liste paginée des membres actifs (`status = 1`), triée par `lastName ASC, firstName ASC`.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `search` | string | non | Recherche textuelle sur `firstName`, `lastName`, `society`, `npa`, `email`, `address` (LIKE insensible à la casse) |
| `team` | integer | non | Filtre par groupe (ID positif). Valeurs négatives = filtres virtuels (voir section dédiée ci-dessous) |
| `metagroup` | integer | non | Filtre par catégorie de groupes (ID de metagroup). Inclut dans la réponse le champ `groups` |
| `page` | integer | non | Numéro de page, défaut `1` |
| `limit` | integer | non | Résultats par page, défaut `25`, max `2000` |
| `types` | any | non | Si présent (valeur quelconque), ajoute le champ `types` à chaque membre |

#### Réponse

```json
{
  "data": [
    {
      "id": 42,
      "lastName": "Dupont",
      "firstName": "Marie",
      "society": null,
      "email": "marie.dupont@example.com",
      "npa": "1200",
      "address": "Rue de la Paix 3",
      "gender": "f",
      "createdAt": "2022-03-15T00:00:00+01:00",
      "types": [
        { "id": 1, "label": "Don ordinaire", "color": "#4caf50" }
      ]
    }
  ],
  "meta": { "page": 1, "limit": 25, "total": 142 }
}
```

Champ `types` présent uniquement si `?types` est passé. Champ `groups` présent uniquement si `?metagroup` est passé.

#### Exemple curl

```bash
curl -b cookies.txt \
  "https://votre-domaine/api/members?search=dupont&limit=10&types=1"
```

---

### Filtres virtuels sur `GET /api/members`

Les filtres virtuels s'activent en passant une valeur négative spéciale au paramètre `team`. Ils appliquent des sous-requêtes SQL et supportent la pagination standard (`page`, `limit`). Le paramètre `types` est également compatible.

| Valeur `team` | Constante | Description |
|---------------|-----------|-------------|
| `-3` | `FILTER_ALL_EXCEPT_ARCHIVES` | Tous les membres actifs (équivalent à l'absence de filtre) |
| `-4` | `FILTER_UNPAID_COTI_CURRENT` | Membres du groupe "membre" (configuré via `membre_team`) qui n'ont pas payé de cotisation dans l'année civile en cours. Retourne un tableau vide si `membre_team` n'est pas configuré |
| `-3333` | `FILTER_UNPAID_COTI_3Y` | Membres ayant payé au moins une cotisation dans l'historique, mais aucune depuis le début de l'année N-2. Exclut les membres du groupe "no_coti" si configuré |
| `-5555` | `FILTER_NO_ACTIVITY_10Y` | Membres actifs sans aucune écriture comptable (`compta`) dans les 10 dernières années |
| `-6666` | `FILTER_NON_INSTIT_LAST_YEAR` | Membres actifs ayant effectué au moins un paiement non-institutionnel (`is_institutional = 0`) durant l'année civile précédente |

La réponse a le même format que `GET /api/members` (envelope `data` + `meta`).

```bash
# Membres avec cotisation en retard depuis plus de 3 ans
curl -b cookies.txt "https://votre-domaine/api/members?team=-3333&limit=50"

# Membres du groupe "membre" sans cotisation cette année
curl -b cookies.txt "https://votre-domaine/api/members?team=-4"
```

---

### `GET /api/members/{id}`

Fiche complète d'un membre.

#### Paramètres de chemin

| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | integer | Identifiant du membre |

#### Réponse

```json
{
  "data": {
    "id": 42,
    "lastName": "Dupont",
    "firstName": "Marie",
    "society": null,
    "gender": "f",
    "title": "Mme",
    "address": "Rue de la Paix 3",
    "npa": "1200",
    "email": "marie.dupont@example.com",
    "tel": "+41 21 000 00 00",
    "telProf": null,
    "portable": "+41 79 000 00 00",
    "fax": null,
    "web": null,
    "birthDate": "1985-06-20",
    "comment": null,
    "createdAt": "2022-03-15T00:00:00+01:00",
    "updatedAt": "2024-01-10T14:32:00+01:00"
  }
}
```

Valeurs `gender` possibles : `"m"`, `"f"`, `"hf"`, `"na"`.

`birthDate`, `createdAt`, `updatedAt` sont `null` si non renseignés.

#### Erreurs

- `404` — membre introuvable (ou inactif)

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/members/42"
```

---

### `POST /api/members`

Créer un nouveau membre. Requiert le rôle **canWrite**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `firstName` | string | **oui** | Prénom |
| `lastName` | string | **oui** | Nom de famille |
| `society` | string | non | Nom de société ou organisation |
| `gender` | string | non | `"m"`, `"f"`, `"hf"` ou `"na"` (défaut `"na"`) |
| `title` | string | non | Civilité |
| `address` | string | non | Adresse postale |
| `npa` | string | non | Code postal + localité |
| `email` | string | non | Adresse e-mail |
| `tel` | string | non | Téléphone fixe |
| `telProf` | string | non | Téléphone professionnel |
| `portable` | string | non | Téléphone mobile |
| `fax` | string | non | Fax |
| `web` | string | non | URL du site web |
| `birthDate` | string | non | Date de naissance au format `YYYY-MM-DD` |
| `comment` | string | non | Commentaire interne |

#### Réponse

`201 Created` — objet membre complet (même structure que `GET /api/members/{id}`).

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/members" \
  -H "Content-Type: application/json" \
  -d '{"firstName": "Marie", "lastName": "Dupont", "email": "marie@example.com"}'
```

---

### `PUT /api/members/{id}` / `PATCH /api/members/{id}`

Modification d'un membre. Seuls les champs présents dans le corps sont modifiés. Chaque modification est enregistrée dans l'audit log avec la valeur avant et après.

Requiert le rôle **canWrite**.

#### Paramètres de chemin

| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | integer | Identifiant du membre |

#### Corps de requête (JSON)

Tous les champs sont optionnels. N'inclure que les champs à modifier.

| Champ | Type | Description |
|-------|------|-------------|
| `firstName` | string | Prénom |
| `lastName` | string | Nom de famille |
| `society` | string | Nom de société ou organisation |
| `gender` | string | `"m"`, `"f"`, `"hf"` ou `"na"` |
| `title` | string | Civilité (ex. `"M."`, `"Mme"`) |
| `address` | string | Adresse postale |
| `npa` | string | Code postal + localité |
| `email` | string | Adresse e-mail |
| `tel` | string | Téléphone fixe |
| `telProf` | string | Téléphone professionnel |
| `portable` | string | Téléphone mobile |
| `fax` | string | Fax |
| `web` | string | URL du site web |
| `birthDate` | string | Date de naissance au format `YYYY-MM-DD`, ou `""` pour effacer |
| `comment` | string | Commentaire interne |

#### Réponse

Objet membre complet après modification (même structure que `GET /api/members/{id}`).

#### Erreurs

- `403` — rôle insuffisant
- `404` — membre introuvable

#### Exemple curl

```bash
curl -b cookies.txt \
  -X PATCH "https://votre-domaine/api/members/42" \
  -H "Content-Type: application/json" \
  -d '{"email": "nouveau@example.com", "portable": "+41 79 111 11 11"}'
```

---

### `DELETE /api/members/{id}`

Désactive un membre (`status = 0`). Avec `?dispose=delete`, supprime définitivement l'enregistrement (requiert le rôle **admin**).

#### Paramètres de query

| Paramètre | Type | Description |
|-----------|------|-------------|
| `dispose` | string | Si `"delete"` : suppression définitive (admin uniquement). Par défaut : désactivation |

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — membre introuvable

#### Exemple curl

```bash
# Désactivation (status=0)
curl -b cookies.txt -X DELETE "https://votre-domaine/api/members/42"

# Suppression définitive (admin)
curl -b cookies.txt -X DELETE "https://votre-domaine/api/members/42?dispose=delete"
```

---

### `GET /api/members/{id}/groups`

Liste des groupes auxquels appartient un membre, triés par catégorie puis par nom.

#### Paramètres de chemin

| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | integer | Identifiant du membre |

#### Réponse

```json
{
  "data": [
    {
      "id": 7,
      "name": "Conseil",
      "hidden": false,
      "categoryId": 2,
      "categoryName": "Organes"
    },
    {
      "id": 12,
      "name": "Membres actifs",
      "hidden": false,
      "categoryId": null,
      "categoryName": null
    }
  ]
}
```

`categoryId` et `categoryName` sont `null` si le groupe n'appartient à aucune catégorie (metagroup).

#### Erreurs

- `404` — membre introuvable ou inactif

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/members/42/groups"
```

---

## Groupes

### `GET /api/groups`

Liste de tous les groupes avec leur nombre de membres actifs, triés par catégorie puis par nom.

#### Réponse

```json
{
  "data": [
    {
      "id": 7,
      "name": "Conseil",
      "hidden": false,
      "memberCount": 8,
      "categoryId": 2,
      "categoryName": "Organes"
    },
    {
      "id": 12,
      "name": "Membres actifs",
      "hidden": false,
      "memberCount": 134,
      "categoryId": null,
      "categoryName": null
    }
  ]
}
```

`memberCount` ne comptabilise que les membres ayant `status = 1`.

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/groups"
```

---

### `POST /api/groups`

Créer un groupe. Requiert le rôle **manager**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `name` | string | **oui** | Nom du groupe |
| `hidden` | boolean | non | `true` pour masquer le groupe (défaut `false`) |

#### Réponse

`201 Created` — objet groupe créé.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/groups" \
  -H "Content-Type: application/json" \
  -d '{"name": "Nouveaux membres 2025"}'
```

---

### `GET /api/groups/{id}`

Détail d'un groupe avec son nombre de membres actifs.

#### Paramètres de chemin

| Paramètre | Type | Description |
|-----------|------|-------------|
| `id` | integer | Identifiant du groupe |

#### Réponse

```json
{
  "data": {
    "id": 7,
    "name": "Conseil",
    "hidden": false,
    "memberCount": 8,
    "categoryId": 2,
    "categoryName": "Organes"
  }
}
```

#### Erreurs

- `404` — groupe introuvable

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/groups/7"
```

---

### `PUT /api/groups/{id}`

Renommer un groupe ou basculer sa visibilité. Requiert le rôle **manager**.

#### Corps de requête (JSON)

| Champ | Type | Description |
|-------|------|-------------|
| `name` | string | Nouveau nom du groupe |
| `hidden` | boolean | `true` pour masquer, `false` pour afficher |

#### Réponse

Objet groupe mis à jour.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/groups/7" \
  -H "Content-Type: application/json" \
  -d '{"name": "Conseil 2025", "hidden": false}'
```

---

### `DELETE /api/groups/{id}`

Supprimer un groupe. Requiert le rôle **manager**. Retourne `409` si le groupe contient des membres actifs.

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — groupe introuvable
- `409` — groupe non vide

#### Exemple curl

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/groups/7"
```

---

### `GET /api/groups/{id}/members`

Liste des membres actifs d'un groupe.

#### Réponse

```json
{
  "data": [
    {
      "id": 42,
      "lastName": "Dupont",
      "firstName": "Marie",
      "email": "marie.dupont@example.com"
    }
  ]
}
```

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/groups/7/members"
```

---

### `POST /api/groups/{id}/members`

Ajouter un membre à un groupe. Requiert le rôle **manager**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre à ajouter |

#### Réponse

`201 Created`

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/groups/7/members" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42}'
```

---

### `DELETE /api/groups/{id}/members`

Retirer un membre d'un groupe. Requiert le rôle **manager**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre à retirer |

#### Réponse

`204 No Content`

#### Exemple curl

```bash
curl -b cookies.txt \
  -X DELETE "https://votre-domaine/api/groups/7/members" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42}'
```

---

## Comptabilité

### `GET /api/compta`

Liste des écritures comptables d'un membre, triées par date décroissante.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre |
| `year` | integer | non | Filtre sur l'année civile (ex. `2024`). Si absent, toutes les années sont retournées |

#### Réponse

```json
{
  "data": [
    {
      "id": 301,
      "memberId": 42,
      "typeId": 1,
      "date": "2024-05-10",
      "label": "Don printemps 2024",
      "amount": 150.00,
      "receipt": "QR-2024-0301",
      "wantsAttestation": true
    }
  ]
}
```

#### Erreurs

- `400` — `memberId` manquant

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/compta?memberId=42&year=2024"
```

---

### `POST /api/compta`

Créer une écriture comptable. Requiert le rôle **canWrite**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre |
| `typeId` | integer | **oui** | Identifiant du type comptable |
| `date` | string | **oui** | Date au format `YYYY-MM-DD` |
| `amount` | number | **oui** | Montant en CHF |
| `label` | string | non | Libellé de l'écriture |
| `receipt` | string | non | Référence quittance |
| `wantsAttestation` | boolean | non | L'adhérent souhaite une attestation de don |

#### Réponse

`201 Created` — objet écriture créée.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/compta" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42, "typeId": 1, "date": "2025-03-15", "amount": 200}'
```

---

### `GET /api/compta/{id}`

Détail d'une écriture comptable.

#### Réponse

Même structure qu'un élément de `GET /api/compta`.

#### Erreurs

- `404` — écriture introuvable

---

### `PUT /api/compta/{id}`

Modifier une écriture comptable. Requiert le rôle **canWrite**. Seuls les champs présents sont mis à jour.

#### Corps de requête (JSON)

Mêmes champs que `POST /api/compta`, tous optionnels sauf `memberId` (non modifiable).

#### Réponse

Objet écriture mis à jour.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/compta/301" \
  -H "Content-Type: application/json" \
  -d '{"amount": 250, "wantsAttestation": true}'
```

---

### `DELETE /api/compta/{id}`

Supprimer une écriture comptable. Requiert le rôle **canWrite**.

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — écriture introuvable

#### Exemple curl

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/compta/301"
```

---

### `GET /api/compta-types`

Liste de tous les types d'écritures comptables configurés, triés par `sortOrder` puis `label`.

#### Réponse

```json
{
  "data": [
    {
      "id": 1,
      "label": "Don ordinaire",
      "color": "#4caf50",
      "sortOrder": 10,
      "isCotisation": false,
      "isExcludedFromDonation": false
    },
    {
      "id": 2,
      "label": "Cotisation annuelle",
      "color": "#2196f3",
      "sortOrder": 20,
      "isCotisation": true,
      "isExcludedFromDonation": false
    }
  ]
}
```

| Champ | Description |
|-------|-------------|
| `isCotisation` | `true` si ce type est une cotisation (utilisé par les filtres virtuels `-4` et `-3333`) |
| `isExcludedFromDonation` | `true` si ce type est exclu du total des dons |
| `color` | Couleur hexadécimale pour l'affichage, ou `null` si non définie |

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/compta-types"
```

---

## Suivi

### `GET /api/suivi`

Liste des notes de suivi d'un membre, triées par date décroissante.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre |

#### Réponse

```json
{
  "data": [
    {
      "id": 88,
      "memberId": 42,
      "date": "2024-11-03",
      "note": "Appel téléphonique. Confirme renouvellement de la cotisation."
    }
  ]
}
```

#### Erreurs

- `400` — `memberId` manquant

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/suivi?memberId=42"
```

---

### `POST /api/suivi`

Créer une note de suivi. Requiert le rôle **canWrite**.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Identifiant du membre |
| `date` | string | **oui** | Date au format `YYYY-MM-DD` |
| `note` | string | **oui** | Contenu de la note |

#### Réponse

`201 Created` — objet note créée.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/suivi" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42, "date": "2025-06-30", "note": "Appel téléphonique. Renouvellement confirmé."}'
```

---

### `GET /api/suivi/{id}`

Détail d'une note de suivi.

#### Réponse

Même structure qu'un élément de `GET /api/suivi`.

#### Erreurs

- `404` — note introuvable

---

### `PUT /api/suivi/{id}`

Modifier une note de suivi. Requiert le rôle **canWrite**.

#### Corps de requête (JSON)

| Champ | Type | Description |
|-------|------|-------------|
| `date` | string | Nouvelle date au format `YYYY-MM-DD` |
| `note` | string | Nouveau contenu |

#### Réponse

Objet note mis à jour.

#### Exemple curl

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/suivi/88" \
  -H "Content-Type: application/json" \
  -d '{"note": "Appel téléphonique. Renouvellement confirmé. Attestation demandée."}'
```

---

### `DELETE /api/suivi/{id}`

Supprimer une note de suivi. Requiert le rôle **canWrite**.

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — note introuvable

#### Exemple curl

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/suivi/88"
```
