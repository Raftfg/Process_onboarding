# 🚀 Démarrage Rapide - Intégration du Microservice

## En 3 étapes

### 1. Obtenir votre clé API

Contactez l'équipe MedKey pour obtenir votre clé API :
- Email: support@medkey.com
- Ou créez-en une dans votre tableau de bord

### 2. Utiliser l'API

**Endpoint principal :**
```
POST https://onboarding.medkey.com/api/onboarding/create
```

**Headers requis :**
```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

### 3. Exemple minimal (JavaScript)

```javascript
fetch('https://onboarding.medkey.com/api/onboarding/create', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_API_KEY'
  },
  body: JSON.stringify({
    hospital: {
      name: 'Mon Hôpital'
    },
    admin: {
      first_name: 'Jean',
      last_name: 'Dupont',
      email: 'admin@monhopital.fr',
      password: 'MonMotDePasse123!'
    }
  })
})
.then(res => res.json())
.then(data => {
  console.log('Créé !', data.data.url);
  window.location.href = data.data.url; // Rediriger vers le dashboard
});
```

## 📚 Documentation complète

Pour plus de détails, consultez :
- **[Guide d'intégration complet](INTEGRATION.md)** - Documentation détaillée
- **[Exemples de code](examples/)** - Exemples pour différents langages

## 🆘 Besoin d'aide ?

- 📧 Email: support@medkey.com
- 📖 Documentation: [INTEGRATION.md](INTEGRATION.md)
