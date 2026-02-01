# 🎯 Guide de Démonstration : Microservice d'Onboarding

Ce guide est conçu pour vous aider à démontrer la valeur et la réutilisabilité du microservice à vos collègues.

## 1. La Preuve par l'Agnosticisme (Générique)

**Ce qu'il faut dire :** *"Regardez, le service n'est plus seulement pour les hôpitaux. Il accepte n'importe quelle organisation."*

**Action :** Montrez l'appel API dans `test_api.php` ou Postman avec un payload non médical :
```json
{
  "organization": {
    "name": "Boulangerie Moderne",
    "email": "contact@boulangerie.com"
  },
  "admin": { "email": "chef@boulangerie.com" }
}
```
**Résultat à montrer :** La création immédiate d'une base `akasigroup_boulangerie_...` prouve que le moteur est devenu universel.

---

## 2. La Preuve de l'Interconnexion (Webhooks + HMAC)

**Ce qu'il faut dire :** *"Vos applications (Python, Go, Node, PHP) seront prévenues en temps réel dès qu'un client termine son inscription, de manière ultra-sécurisée."*

**Démonstration :**
1. Lancez le récepteur de démo : `php -S localhost:9000 webhook_demo_receiver.php`
2. Enregistrez ce webhook via l'API.
3. Simulez un onboarding.
4. **Le clou du spectacle :** Montrez dans le terminal du récepteur que la signature HMAC a été vérifiée. Cela prouve que personne ne peut envoyer de fausses notifications à leur application.

---

## 3. La Preuve de l'Isolation (Multi-Tenancy)

**Ce qu'il faut dire :** *"Chaque projet a sa propre base de données physique. Aucune fuite de données n'est possible entre les clients."*

**Action :** Montrez le dossier des bases de données MySQL (ou via Tinker) :
```bash
php artisan tinker --execute="DB::select('SHOW DATABASES LIKE \'akasigroup_%\'')"
```
On y voit une base par organisation, isolées du "Control Plane" central.

---

## 4. La Preuve de Simplicité (Dev-First)

**Ce qu'il faut dire :** *"L'intégration prend 5 minutes. Tout est documenté selon les standards OpenAPI."*

**Action :**
- Ouvrez le fichier `openapi.yaml` dans Swagger Editor.
- Montrez le fichier `INTEGRATION.md` avec les exemples de code en **JavaScript**, **PHP**, et **cURL**.

---

## Conclusion pour vos collègues
*"C'est un composant 'Plug & Play'. Vous n'avez plus à réinventer la création de base de données, la gestion de sous-domaine ou l'envoi d'emails d'activation. Appelez l'API, écoutez le Webhook, et concentrez-vous sur votre métier."*
