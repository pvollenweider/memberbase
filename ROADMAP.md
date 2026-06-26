# Roadmap — Casa Alianza Membres

Chantiers identifiés, classés par priorité et effort estimé. Mettre à jour au fil des releases.

---

## Prêt à démarrer

### Détection de doublons (Intégrité)
**Où :** `?view=settings&tab=integrity`  
**Quoi :** détecter les membres avec même prénom+nom ou même email. Afficher dans l'onglet Intégrité existant, avec lien vers les fiches concernées.  
**Effort :** faible — requête SQL `GROUP BY` + `HAVING COUNT > 1`, rendu dans `integrity.inc`.

### Journal d'activité — recherche et filtres
**Où :** `?view=settings&tab=audit`  
**Quoi :** ajouter un champ de recherche textuelle + filtre par utilisateur et par action dans la vue journal existante.  
**Effort :** faible — filtrage côté serveur ou DataTables client.

### Journal d'activité — export CSV/Excel
**Où :** `?view=settings&tab=audit`  
**Quoi :** bouton d'export DataTables (CSV/Excel) sur la vue journal.  
**Effort :** très faible — activer les boutons DataTables déjà présents ailleurs.

### Historique des changements par membre
**Où :** fiche membre (`?view=updateUser&id=X`)  
**Quoi :** onglet ou section "Historique" sur la fiche membre, filtrant `audit_log` sur l'ID du membre. Montre qui a modifié quoi et quand.  
**Effort :** faible — `audit_log` existe, il faut normaliser le champ `detail` pour inclure `user_id` et filtrer dessus.

### Remplacer `data-href` TR par liens explicites
**Où :** vues avec lignes de tableau cliquables (`lastEntryCompta`, `lastEntrySuivi`, résumé…)  
**Quoi :** remplacer le pattern `data-href` + JS par un `<a>` qui enveloppe la ligne ou un bouton explicite par ligne. Améliore accessibilité clavier et screen readers.  
**Effort :** moyen — touche plusieurs vues, à faire vue par vue.

---

## Planifié

### Vues htmx complètes
**Quoi :** certaines vues rechargent encore toute la page alors que la fondation htmx est en place. Passer les actions CRUD courantes (ajout/suppression dans les listes) en partiel htmx pour éviter les rechargements complets.  
**Effort :** moyen — à évaluer vue par vue.

### Migration `getMaxVal` → `AUTO_INCREMENT`
**Quoi :** supprimer la table `maxval` et la fonction `getMaxVal()`. Migrer `team` et `metagroup` vers `AUTO_INCREMENT` natif, utiliser `lastInsertId()` après les `INSERT`.  
**Impact :** touche les classes `Team` et `Metagroup` et `manage_actions.inc`.  
**Effort :** moyen — migration SQL + refactor classes + tests avant prod.  
**Migration SQL :**
```sql
ALTER TABLE team     MODIFY id INT AUTO_INCREMENT;
ALTER TABLE metagroup MODIFY id INT AUTO_INCREMENT;
-- Synchroniser AUTO_INCREMENT à la valeur max existante
ALTER TABLE team      AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM team);
ALTER TABLE metagroup AUTO_INCREMENT = (SELECT MAX(id) + 1 FROM metagroup);
DROP TABLE maxval;
```

### Tests automatisés — extension de la suite Cypress
**Quoi :** étendre la suite E2E existante. Priorités : flux compta (ajout/suppression d'entrée), génération d'attestation PDF, gestion des groupes (rename inline, import).  
**Effort :** moyen — infrastructure Cypress en place, il faut écrire les specs.

### Attestations de dons — envoi par email + modèles
**Quoi :** depuis la vue résumé ou la fiche membre, envoyer l'attestation PDF par email directement. Prévoir une gestion de modèles d'email (objet, corps) configurables dans les Réglages.  
**Dépendance :** nécessite un serveur SMTP configuré (ou intégration Mailchimp Transactional / SendGrid).  
**Effort :** élevé — UI modèles + intégration SMTP + file d'envoi pour le bulk.

---

## Backlog (à planifier)

### Intégration Mailchimp
**Quoi :** synchroniser des listes de membres vers des audiences Mailchimp (ou autre ESP). Déclencher des campagnes depuis l'app (relance cotisation, newsletter donateurs).  
**Effort :** élevé — API Mailchimp, mapping champs, UI de synchronisation.

### Import CSV de membres
**Quoi :** créer ou mettre à jour des membres en masse depuis un fichier CSV/Excel. Utile pour une migration ou un import annuel.  
**Effort :** moyen — parsing CSV, validation, UI de prévisualisation avant import.

### Déploiement automatisé (CI/CD)
**Quoi :** GitHub Actions → SSH → rsync sur push d'un tag. Remplace le rsync manuel.  
**Effort :** faible une fois mis en place — à faire quand le processus de release sera plus fréquent.

---

## Hors scope (décision)

- **Pagination server-side DataTables** — pas nécessaire à l'échelle actuelle
- **Filtres combinés dans la liste membres** — les métagroupes couvrent le besoin
