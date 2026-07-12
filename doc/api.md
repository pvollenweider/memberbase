# Référence API MemberBase

API REST interne de l'application MemberBase (version **5.1.0**). Toutes les réponses sont en JSON UTF-8.

> ⚠️ **v5.0.0 (breaking)** : les routes `/api/members` et `/api/groups` sont renommées `/api/contacts` et `/api/segments` (aucune rétrocompatibilité). Voir le [CHANGELOG](../CHANGELOG.md#500--2026-07-09).

## Sommaire

- [Authentification](#authentification)
- [Rôles et permissions](#rôles-et-permissions)
- [Format des réponses](#format-des-réponses)
- [Routage (.htaccess)](#routage-htaccess)
- [Membres](#membres)
- [Segments](#segments)
- [Comptabilité](#comptabilité)
- [Types de compta](#types-de-compta)
- [Suivi](#suivi)

---

## Authentification

L'API ne dispose pas de token séparé. Elle repose sur **la session web de l'application** : la requête doit porter le cookie de session obtenu lors d'une connexion via l'interface web (`login.php`).

Le garde d'authentification est appliqué en amont de chaque endpoint par `html/api/_bootstrap.php` : si la session est absente ou expirée (`isLoggedIn()` faux), **toutes les routes retournent `401`** avant tout traitement.

```bash
# Login via le formulaire web, puis réutilisation du cookie
curl -c cookies.txt \
  -X POST https://votre-domaine/login \
  -d "username=alice&password=secret"

# Toutes les requêtes API suivantes portent ce cookie
curl -b cookies.txt https://votre-domaine/api/contacts
```

---

## Rôles et permissions

Le rôle est porté par la session (`$_SESSION['app_user_role']`). Quatre rôles hiérarchiques sont définis dans `html/includes/lib/auth.php` :

| Rôle | `canRead()` | `canWrite()` | `isManager()` | `isAdmin()` |
|------|:-----------:|:------------:|:-------------:|:-----------:|
| `readonly` | ✅ | — | — | — |
| `user` | ✅ | ✅ | — | — |
| `manager` | ✅ | ✅ | ✅ | — |
| `admin` | ✅ | ✅ | ✅ | ✅ |

Règles réelles appliquées **par endpoint** (vérifiées dans le code — elles ne sont pas homogènes entre ressources) :

- **Membres, Compta, Suivi** : les lectures appellent explicitement `canRead()` (403 si rôle insuffisant) ; les écritures appellent `canWrite()`.
- **Suppression définitive d'un membre** (`DELETE /api/contacts/{id}?dispose=delete`) : `isAdmin()` requis (403 sinon). La désactivation simple ne requiert que `canWrite()` implicite (aucun contrôle de rôle explicite au-delà de l'authentification pour la désactivation — voir la note de l'endpoint).
- **Segments / groups** : toutes les écritures (création, mise à jour, suppression, ajout/retrait de membre) appellent `isManager()` (403 sinon). **Les lectures (`GET`) n'effectuent AUCUN contrôle de rôle** : elles ne requièrent que l'authentification (n'importe quel rôle connecté, y compris `readonly`).
- **Types de compta** (`GET /api/compta-types`) : **aucun contrôle de rôle** au-delà de l'authentification.

Un rôle insuffisant retourne `403` avec `{ "error": "Forbidden" }` (ou un message spécifique, ex. `"Manager role required"`, `"Admin role required to permanently delete a member"`).

> **Terminologie.** L'interface et l'API parlent de **Segment** (chemins `/api/segments`, entité SQL `segment`, depuis la v5.0.0). Le paramètre de filtre `GET /api/contacts?segment=` s'appelait `team` avant la v5.1.0 — **aucune rétrocompatibilité**, tout appelant doit utiliser `segment`. Dans ce document, « Segment » et « groupe » sont synonymes.

---

## Format des réponses

- **Content-Type** : `application/json; charset=utf-8`
- **Envelope succès** : `{ "data": … }` (objet ou tableau)
- **Envelope liste paginée** : `{ "data": […], "meta": { "page": 1, "limit": 25, "total": 142 } }` (uniquement `GET /api/contacts`)
- **Envelope liste simple** : `{ "data": […] }` (compta, suivi, groups, compta-types, members/{id}/groups, groups/{id}/members)
- **Erreur** : `{ "error": "message lisible" }`

Encodage JSON : `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

### Codes HTTP utilisés

| Code | Signification |
|------|---------------|
| `200` | Succès (GET, PUT/PATCH) |
| `201` | Ressource créée (POST) |
| `204` | Succès sans corps (DELETE, ajout/retrait de membre de groupe) |
| `400` | Paramètre de query manquant / corps JSON invalide / filtre virtuel inconnu |
| `401` | Non authentifié (session absente ou expirée) |
| `403` | Rôle insuffisant |
| `404` | Ressource introuvable |
| `405` | Méthode non autorisée |
| `409` | Conflit (groupe non vide) |
| `422` | Données manquantes ou invalides dans le corps |
| `500` | Erreur serveur interne |

---

## Routage (.htaccess)

Le routage est assuré par `html/api/.htaccess`. Chemins exposés :

| Méthode + chemin | Script cible |
|------------------|--------------|
| `/api/contacts` | `contacts.php` |
| `/api/contacts/{id}` | `contacts.php?id={id}` |
| `/api/contacts/{id}/groups` | `contacts.php?id={id}&sub=groups` |
| `/api/contacts/{id}?sub=compta` | `contacts.php?id={id}&sub=compta` (pas de clean-URL dédiée) |
| `/api/compta` | `compta.php` |
| `/api/compta/{id}` | `compta.php?id={id}` |
| `/api/compta-types` | `compta-types.php` |
| `/api/suivi` | `suivi.php` |
| `/api/suivi/{id}` | `suivi.php?id={id}` |
| `/api/segments` | `segments.php` |
| `/api/segments/{id}` | `segments.php?id={id}` |
| `/api/segments/{id}/members` | `segments.php?id={id}&sub=members` |

Toute combinaison méthode/chemin non prévue par le dispatcher d'un script retourne `405 Method Not Allowed`.

---

## Membres

Ressource : `html/api/contacts.php`. Entité SQL : `contact` (renommée depuis `users` en v5.0.0).

### `GET /api/contacts`

Liste paginée des membres **actifs** (`contact.status = 1`), triée par `lastName ASC, firstName ASC`.
Rôle : `canRead()`.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `search` | string | non | Recherche `LIKE %…%` sur `firstname`, `lastname`, `firstname lastname`, `lastname firstname`, `society`, `npa`, `email`, `address` |
| `segment` | integer | non | Filtre par groupe (ID positif). Valeur **négative** = filtre virtuel (voir section dédiée) |
| `combinedSegment` | integer | non | Filtre par catégorie de groupes (ID de `combined_segment`). Ajoute le champ `groups` à chaque membre. Renvoie une liste vide si la catégorie n'a aucun groupe rattaché |
| `page` | integer | non | Numéro de page, défaut `1` (borné à ≥ 1) |
| `limit` | integer | non | Résultats par page, défaut `25`, borné à `[1, 2000]` |
| `types` | any | non | Si présent (valeur quelconque, même vide), ajoute le champ `types` à chaque membre |

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
      ],
      "groups": [
        { "id": 12, "name": "Membres actifs" }
      ]
    }
  ],
  "meta": { "page": 1, "limit": 25, "total": 142 }
}
```

- `types` présent uniquement si `?types` est passé (tableau `{id, label, color}` ; `color` est `""` si non définie).
- `groups` présent uniquement si `?combinedSegment` est passé (tableau `{id, name}` des groupes de la catégorie auxquels le membre appartient).

#### Exemple curl

```bash
curl -b cookies.txt \
  "https://votre-domaine/api/contacts?search=dupont&limit=10&types=1"
```

---

### Filtres virtuels sur `GET /api/contacts`

Activés en passant une valeur **négative** au paramètre `segment`. La résolution est déléguée à la classe partagée `MemberFilter` (`html/classes/member_filter_class.php`) — la même que celle utilisée par la liste des membres dans l'interface, ce qui garantit des résultats identiques entre la vue et l'API. Pagination standard (`page`, `limit`) et `types` supportés. Une valeur négative non reconnue retourne `400 Unknown virtual filter`.

Constantes définies dans `html/includes/lib/bootstrap.php` :

| Valeur `segment` | Constante | Description |
|---------------|-----------|-------------|
| `-3` | `FILTER_ALL_EXCEPT_ARCHIVES` | Tous les membres actifs (équivalent à l'absence de filtre) |
| `-4` | `FILTER_UNPAID_COTI_CURRENT` | Membres du groupe « membre » (paramètre `membre_segment`) sans cotisation payée dans l'année civile en cours. Exclut le groupe « no_coti » (`member_no_coti_segment`) si configuré. Retourne un tableau vide si `membre_segment` n'est pas configuré |
| `-3333` | `FILTER_UNPAID_COTI_3Y` | Membres ayant payé au moins une cotisation dans l'historique, mais aucune depuis le début de l'année N-2. Exclut le groupe « no_coti » si configuré |
| `-5555` | `FILTER_NO_ACTIVITY_10Y` | Membres actifs sans aucune écriture comptable (`compta`) dans les 10 dernières années |
| `-6666` | `FILTER_NON_INSTIT_LAST_YEAR` | Membres actifs ayant effectué au moins un paiement non-institutionnel (type `is_institutional = 0`, ou sans type) durant l'année civile précédente |

Une cotisation est une écriture dont le type a `is_cotisation = 1`. La réponse a le même format que `GET /api/contacts` (envelope `data` + `meta`).

```bash
# Cotisation en retard depuis plus de 3 ans
curl -b cookies.txt "https://votre-domaine/api/contacts?segment=-3333&limit=50"

# Membres du groupe « membre » sans cotisation cette année
curl -b cookies.txt "https://votre-domaine/api/contacts?segment=-4"
```

---

### `GET /api/contacts/{id}`

Fiche complète d'un membre. Rôle : `canRead()`.

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
    "emailAlt": null,
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

Valeurs `gender` possibles : `"m"`, `"f"`, `"hf"`, `"na"`. `birthDate` est `YYYY-MM-DD` ou `null`. `createdAt`/`updatedAt` sont au format ISO 8601 (`date('c')`) ou `null`.

#### Erreurs

- `404` — membre introuvable. Note : `lookupUser()` ne filtre pas sur `status`, un membre désactivé peut donc être retourné par son id.

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/contacts/42"
```

---

### `POST /api/contacts`

Créer un membre. Rôle : `canWrite()`.

#### Corps de requête (JSON)

Champs de la liste blanche (`applyFields`). **Seul `lastName` est obligatoire** (rejet `422` si vide) ; tous les autres champs sont facultatifs.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `lastName` | string | **oui** | Nom de famille |
| `firstName` | string | non | Prénom |
| `society` | string | non | Société / organisation |
| `gender` | string | non | `"m"`, `"f"`, `"hf"` ou `"na"`. Toute autre valeur est normalisée en `"na"` (défaut) |
| `title` | string | non | Civilité |
| `address` | string | non | Adresse postale |
| `npa` | string | non | Code postal + localité |
| `email` | string | non | E-mail |
| `emailAlt` | string | non | E-mail secondaire |
| `tel` | string | non | Téléphone fixe |
| `telProf` | string | non | Téléphone professionnel |
| `portable` | string | non | Téléphone mobile |
| `fax` | string | non | Fax |
| `web` | string | non | URL du site web |
| `birthDate` | string | non | Date de naissance `YYYY-MM-DD` |
| `comment` | string | non | Commentaire interne |

#### Réponse

`201 Created` — objet membre complet (même structure que `GET /api/contacts/{id}`).

#### Erreurs

- `400` — corps JSON invalide
- `403` — rôle insuffisant
- `422` — `lastName` manquant/vide

#### Exemple curl

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/contacts" \
  -H "Content-Type: application/json" \
  -d '{"lastName": "Dupont", "firstName": "Marie", "email": "marie@example.com"}'
```

---

### `PUT /api/contacts/{id}` / `PATCH /api/contacts/{id}`

Modification partielle : seuls les champs présents dans le corps sont appliqués. Chaque modification est journalisée dans l'audit log (valeur avant → après). Rôle : `canWrite()`.

Champs acceptés : identiques à `POST` (tous facultatifs). `birthDate: ""` efface la date de naissance.

#### Réponse

Objet membre complet après modification (structure de `GET /api/contacts/{id}`).

#### Erreurs

- `400` — corps JSON invalide
- `403` — rôle insuffisant
- `404` — membre introuvable

#### Exemple curl

```bash
curl -b cookies.txt \
  -X PATCH "https://votre-domaine/api/contacts/42" \
  -H "Content-Type: application/json" \
  -d '{"email": "nouveau@example.com", "portable": "+41 79 111 11 11"}'
```

---

### `DELETE /api/contacts/{id}`

Par défaut : **désactivation** (`UPDATE contact SET status = 0`). Avec `?dispose=delete` : **suppression définitive** de l'enregistrement (`isAdmin()` requis).

#### Paramètres de query

| Paramètre | Type | Description |
|-----------|------|-------------|
| `dispose` | string | `"delete"` : suppression définitive (admin uniquement). Toute autre valeur (ou absente) : désactivation |

#### Réponse

`204 No Content`

#### Erreurs

- `403` — `dispose=delete` demandé sans rôle `admin`
- `404` — membre introuvable

> La désactivation (`dispose` ≠ `delete`) ne comporte **pas** de contrôle de rôle explicite au-delà de l'authentification dans le code actuel ; seule la suppression définitive vérifie `isAdmin()`.

#### Exemple curl

```bash
# Désactivation (status=0)
curl -b cookies.txt -X DELETE "https://votre-domaine/api/contacts/42"

# Suppression définitive (admin)
curl -b cookies.txt -X DELETE "https://votre-domaine/api/contacts/42?dispose=delete"
```

---

### `GET /api/contacts/{id}/groups`

Groupes (segments) auxquels appartient un membre, triés par catégorie (`sort_order`, `name`) puis par nom de groupe. Rôle : `canRead()`.

#### Réponse

```json
{
  "data": [
    { "id": 7,  "name": "Conseil",        "hidden": false, "categoryId": 2,    "categoryName": "Organes" },
    { "id": 12, "name": "Membres actifs", "hidden": false, "categoryId": null, "categoryName": null }
  ]
}
```

`categoryId`/`categoryName` sont `null` si le groupe n'appartient à aucune catégorie non-filtre (`combined_segment` avec `is_filter = 0`).

#### Erreurs

- `404` — membre introuvable **ou inactif** (contrôle `status = 1` ici, contrairement à `GET /api/contacts/{id}`)

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/contacts/42/groups"
```

---

### `GET /api/contacts/{id}?sub=compta`

Écritures comptables d'un membre, triées par date décroissante (`date DESC, id DESC`). Rôle : `canRead()`.

> Pas de route clean-URL dédiée dans `html/api/.htaccess` (contrairement à `sub=groups` → `/members/{id}/groups`) : seule la forme en query string fonctionne.

#### Réponse

```json
{
  "data": [
    {
      "id": 231,
      "date": "2026-01-15",
      "label": "Cotisation 2026",
      "amount": 50.0,
      "comment": null,
      "wantsAttestation": false,
      "notifiedAt": null,
      "cotisationYear": 2026,
      "type": { "id": 3, "label": "Cotisation", "color": "#4caf50", "isCotisation": true }
    }
  ]
}
```

- `notifiedAt` : date d'envoi du récapitulatif email incluant cette entrée (`null` = pas encore notifiée), voir [Récapitulatifs comptables](admin.md#) dans le guide administrateur.
- `cotisationYear` : année de cotisation couverte par l'entrée quand elle diffère de l'année de paiement (ex. cotisation 2027 payée en décembre 2026), sinon `null` — dans ce cas l'année effective est celle du champ `date`.
- `type` est `null` si l'entrée n'a pas de type associé.

#### Erreurs

- `404` — membre introuvable ou inactif (`status = 1`)

#### Exemple curl

```bash
curl -b cookies.txt "https://votre-domaine/api/contacts/42?sub=compta"
```

---

## Segments

Ressource : `html/api/segments.php`. Entité SQL : `segment` (renommée depuis `team` en v5.0.0).

> **Contrôle d'accès.** Les lectures (`GET`) ne vérifient **que** l'authentification (aucun `canRead()`). Toutes les écritures vérifient `isManager()` → `403 "Manager role required"` sinon.

### `GET /api/segments`

Liste de tous les groupes avec leur nombre de membres actifs, triés par catégorie puis nom.

#### Réponse

```json
{
  "data": [
    { "id": 7,  "name": "Conseil",        "hidden": false, "memberCount": 8,   "categoryId": 2,    "categoryName": "Organes" },
    { "id": 12, "name": "Membres actifs", "hidden": false, "memberCount": 134, "categoryId": null, "categoryName": null }
  ]
}
```

`memberCount` ne compte que les membres `status = 1`.

```bash
curl -b cookies.txt "https://votre-domaine/api/segments"
```

---

### `GET /api/segments/{id}`

Détail d'un groupe avec nombre de membres actifs. Même structure d'objet que dans la liste.

#### Erreurs

- `404` — groupe introuvable

```bash
curl -b cookies.txt "https://votre-domaine/api/segments/7"
```

---

### `POST /api/segments`

Créer un groupe. Rôle : `isManager()`.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `name` | string | **oui** | Nom du groupe (rejet `422` si vide) |
| `hidden` | boolean | non | `true` pour masquer (défaut `false`) |

#### Réponse

`201 Created` — objet groupe (`memberCount` = 0, `categoryId`/`categoryName` = `null`).

#### Erreurs

- `403` — rôle insuffisant
- `422` — `name` manquant/vide

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/segments" \
  -H "Content-Type: application/json" \
  -d '{"name": "Nouveaux membres 2025"}'
```

---

### `PUT /api/segments/{id}`

Renommer un groupe et/ou basculer sa visibilité. Rôle : `isManager()`. Seuls les champs présents sont appliqués.

| Champ | Type | Description |
|-------|------|-------------|
| `name` | string | Nouveau nom |
| `hidden` | boolean | `true` pour masquer, `false` pour afficher |

#### Réponse

Objet groupe mis à jour.

#### Erreurs

- `403` — rôle insuffisant
- `404` — groupe introuvable

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/segments/7" \
  -H "Content-Type: application/json" \
  -d '{"name": "Conseil 2025", "hidden": false}'
```

---

### `DELETE /api/segments/{id}`

Supprimer un groupe. Rôle : `isManager()`. Refusé (`409`) si le groupe contient encore des membres.

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — groupe introuvable
- `409` — groupe non vide (message : « Group still has members — remove them first or use force=true ». Note : le paramètre `force=true` évoqué dans le message **n'est pas implémenté** dans le code actuel)

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/segments/7"
```

---

### `GET /api/segments/{id}/members`

Membres actifs (`status = 1`) d'un groupe, triés par `lastName, firstName`.

#### Réponse

```json
{
  "data": [
    { "id": 42, "lastName": "Dupont", "firstName": "Marie", "email": "marie.dupont@example.com", "npa": "1200" }
  ]
}
```

#### Erreurs

- `404` — groupe introuvable

```bash
curl -b cookies.txt "https://votre-domaine/api/segments/7/members"
```

---

### `POST /api/segments/{id}/members`

Ajouter un membre à un groupe (`INSERT IGNORE` — idempotent). Rôle : `isManager()`.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre à ajouter |

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — groupe introuvable
- `422` — `memberId` manquant, ou membre inexistant/inactif

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/segments/7/members" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42}'
```

---

### `DELETE /api/segments/{id}/members`

Retirer un membre d'un groupe. Rôle : `isManager()`.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre à retirer |

#### Réponse

`204 No Content` (aucune erreur si l'appartenance n'existait pas).

#### Erreurs

- `403` — rôle insuffisant
- `422` — `memberId` manquant

```bash
curl -b cookies.txt \
  -X DELETE "https://votre-domaine/api/segments/7/members" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42}'
```

---

## Comptabilité

Ressource : `html/api/compta.php`. Entité SQL : `compta`.

### `GET /api/compta`

Écritures comptables d'un membre, triées par `date DESC, id DESC`. Rôle : `canRead()`.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre |
| `year` | integer | non | Filtre sur l'année civile (`date >= 1er janv. de l'année` et `< 1er janv. de l'année+1`) |

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
      "amount": 150.0,
      "comment": "QR-2024-0301",
      "wantsAttestation": true
    }
  ]
}
```

`amount` est un nombre (float) ou `null` ; `label`/`comment` sont `null` si vides.

#### Erreurs

- `400` — `memberId` manquant

```bash
curl -b cookies.txt "https://votre-domaine/api/compta?memberId=42&year=2024"
```

---

### `GET /api/compta/{id}`

Détail d'une écriture. Rôle : `canRead()`. Même structure qu'un élément de liste.

#### Erreurs

- `404` — écriture introuvable

---

### `POST /api/compta`

Créer une écriture. Rôle : `canWrite()`.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre (doit exister et être actif) |
| `typeId` | integer | **oui** | Type comptable (doit exister dans `compta_type`) |
| `date` | string | **oui** | Date `YYYY-MM-DD` (non vide) |
| `amount` | number\|string | **oui** | Montant CHF. La virgule décimale est acceptée (`"150,50"` → `150.50`) |
| `label` | string | non | Libellé |
| `comment` | string | non | Commentaire libre |
| `wantsAttestation` | boolean | non | Souhait d'attestation de don (défaut `false`) |

#### Réponse

`201 Created` — objet écriture.

#### Erreurs

- `403` — rôle insuffisant
- `422` — champ obligatoire manquant, `typeId` inconnu (`"Unknown typeId: N"`), ou membre inexistant/inactif (`"Member #N not found"`)

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/compta" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42, "typeId": 1, "date": "2025-03-15", "amount": 200}'
```

---

### `PUT /api/compta/{id}`

Modifier une écriture. Rôle : `canWrite()`. Seuls les champs présents sont appliqués. **`memberId` n'est pas modifiable** (ignoré).

#### Corps de requête (JSON)

Champs modifiables : `typeId`, `date`, `amount`, `label`, `comment`, `wantsAttestation` (tous facultatifs). Si `typeId` est fourni, il est validé (`422` si inconnu).

#### Réponse

Objet écriture mis à jour.

#### Erreurs

- `403` — rôle insuffisant
- `404` — écriture introuvable
- `422` — `typeId` inconnu

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/compta/301" \
  -H "Content-Type: application/json" \
  -d '{"amount": 250, "wantsAttestation": true}'
```

---

### `DELETE /api/compta/{id}`

Supprimer une écriture. **Rôle : `canWrite()`** (et non `admin` — divergence corrigée par rapport aux versions antérieures de cette doc).

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — écriture introuvable

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/compta/301"
```

---

## Types de compta

Ressource : `html/api/compta-types.php`. Entité SQL : `compta_type`.

### `GET /api/compta-types`

Liste de tous les types comptables configurés (source : le tableau global `$comptaTypes`), triés par `sort_order` puis `label`.

> **Accès** : seule l'authentification est requise (aucun contrôle de rôle). Seul `GET` est accepté ; toute autre méthode → `405`.

#### Réponse

```json
{
  "data": [
    { "id": 1, "label": "Don ordinaire",      "color": "#4caf50", "sortOrder": 10, "isCotisation": false, "isExcludedFromDonation": false },
    { "id": 2, "label": "Cotisation annuelle", "color": "#2196f3", "sortOrder": 20, "isCotisation": true,  "isExcludedFromDonation": false }
  ]
}
```

| Champ | Description |
|-------|-------------|
| `color` | Couleur hexadécimale, ou `null` si non définie |
| `sortOrder` | Ordre d'affichage |
| `isCotisation` | `true` si le type est une cotisation (utilisé par les filtres virtuels `-4` et `-3333`) |
| `isExcludedFromDonation` | `true` si le type est exclu du total des dons |

```bash
curl -b cookies.txt "https://votre-domaine/api/compta-types"
```

---

## Suivi

Ressource : `html/api/suivi.php`. Entité SQL : `contact_properties` avec `parameter = 'suivi'`.

### `GET /api/suivi`

Notes de suivi d'un membre, triées par `date DESC, id DESC`. Rôle : `canRead()`.

#### Paramètres de query

| Paramètre | Type | Obligatoire | Description |
|-----------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre |

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

```bash
curl -b cookies.txt "https://votre-domaine/api/suivi?memberId=42"
```

---

### `GET /api/suivi/{id}`

Détail d'une note. Rôle : `canRead()`. Même structure qu'un élément de liste.

#### Erreurs

- `404` — note introuvable (l'id doit correspondre à une `contact_properties` avec `parameter = 'suivi'`)

---

### `POST /api/suivi`

Créer une note. Rôle : `canWrite()`.

#### Corps de requête (JSON)

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `memberId` | integer | **oui** | Membre (doit exister et être actif) |
| `date` | string | **oui** | Date `YYYY-MM-DD` (non vide) |
| `note` | string | **oui** | Contenu (non vide après trim) |

#### Réponse

`201 Created` — objet note.

#### Erreurs

- `403` — rôle insuffisant
- `422` — champ obligatoire manquant/vide, ou membre inexistant/inactif (`"Member #N not found"`)

```bash
curl -b cookies.txt \
  -X POST "https://votre-domaine/api/suivi" \
  -H "Content-Type: application/json" \
  -d '{"memberId": 42, "date": "2025-06-30", "note": "Appel téléphonique. Renouvellement confirmé."}'
```

---

### `PUT /api/suivi/{id}`

Modifier une note. Rôle : `canWrite()`. Seuls `date` et `note` (présents) sont appliqués.

| Champ | Type | Description |
|-------|------|-------------|
| `date` | string | Nouvelle date `YYYY-MM-DD` |
| `note` | string | Nouveau contenu |

#### Réponse

Objet note mis à jour.

#### Erreurs

- `403` — rôle insuffisant
- `404` — note introuvable

```bash
curl -b cookies.txt \
  -X PUT "https://votre-domaine/api/suivi/88" \
  -H "Content-Type: application/json" \
  -d '{"note": "Appel téléphonique. Renouvellement confirmé. Attestation demandée."}'
```

---

### `DELETE /api/suivi/{id}`

Supprimer une note. Rôle : `canWrite()`.

#### Réponse

`204 No Content`

#### Erreurs

- `403` — rôle insuffisant
- `404` — note introuvable

```bash
curl -b cookies.txt -X DELETE "https://votre-domaine/api/suivi/88"
```
