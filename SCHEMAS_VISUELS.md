# Schémas et Diagrammes - Microservice Onboarding Akasi

Ce document contient tous les schémas visuels pour expliquer le fonctionnement du microservice d'onboarding.

---

## 📐 Schéma 1 : Architecture Générale

![Architecture du Microservice](file:///C:/Users/Akasi/.gemini/antigravity/brain/457f6ebb-ebeb-42f3-a086-3831a98746f7/architecture_microservice_onboarding_1770109384914.png)

**Description** :
Ce schéma montre l'architecture complète du microservice avec :
- L'application cliente qui envoie les requêtes
- Le microservice avec ses composants (API Gateway, Services, Bases de données)
- Les tenants créés avec leurs bases de données isolées
- Les flux de callback et d'email

---

## 🔄 Schéma 2 : Flux d'Onboarding Complet

```mermaid
sequenceDiagram
    participant App as Application Cliente<br/>(SIH, ERP, etc.)
    participant API as Microservice<br/>Onboarding
    participant DB as Base de Données
    participant Email as Service Email
    participant Admin as Admin du Tenant

    Note over App,Admin: Étape 1-4 : Création Synchrone
    
    App->>API: 1. POST /api/v1/onboarding/external<br/>Headers: X-API-Key, X-App-Name<br/>Body: organization, email, migrations
    
    API->>API: 2. Validation API Key<br/>et X-App-Name
    
    API->>DB: 3. Créer nouvelle base<br/>"tenant_clinique_a"
    DB-->>API: Base créée
    
    API->>DB: 4. Exécuter migrations<br/>(défaut + personnalisées)
    DB-->>API: Migrations OK
    
    Note over App,Admin: Étape 5-8 : Notifications Asynchrones
    
    API->>DB: 5. Créer admin user<br/>+ token d'activation
    DB-->>API: User créé
    
    API->>Email: 6. Envoyer email<br/>d'activation
    Email->>Admin: Email avec lien<br/>d'activation
    
    API->>App: 7. Callback POST<br/>avec infos tenant
    
    Admin->>API: 8. Clic sur lien<br/>d'activation
    API-->>Admin: Redirection vers<br/>dashboard tenant
```

**Étapes détaillées** :

### Phase Synchrone (1-4)
1. **Requête initiale** : L'application cliente envoie les données de l'organisation
2. **Validation** : Le microservice vérifie les credentials et le header X-App-Name
3. **Création DB** : Une nouvelle base de données est créée pour le tenant
4. **Migrations** : Les tables par défaut + les tables personnalisées sont créées

### Phase Asynchrone (5-8)
5. **Création admin** : Un compte administrateur est créé avec un token unique
6. **Email** : Un email d'activation est envoyé à l'administrateur
7. **Callback** : Le microservice notifie l'application cliente que le tenant est prêt
8. **Activation** : L'admin clique sur le lien et accède à son espace

---

## 🏢 Schéma 3 : Isolation Multi-App

```mermaid
graph TB
    subgraph "Application 1: SIH-Gabon"
        A1[SIH-Gabon<br/>X-App-Name: SIH-Gabon]
        T1A[Tenant: Clinique A]
        T1B[Tenant: Hôpital B]
        DB1A[(DB: clinique_a)]
        DB1B[(DB: hopital_b)]
        
        A1 --> T1A
        A1 --> T1B
        T1A --> DB1A
        T1B --> DB1B
    end
    
    subgraph "Application 2: ERP-Sante"
        A2[ERP-Sante<br/>X-App-Name: ERP-Sante]
        T2A[Tenant: Clinique A]
        T2C[Tenant: Centre C]
        DB2A[(DB: clinique_a)]
        DB2C[(DB: centre_c)]
        
        A2 --> T2A
        A2 --> T2C
        T2A --> DB2A
        T2C --> DB2C
    end
    
    subgraph "Application 3: Logiciel-Clinique"
        A3[Logiciel-Clinique<br/>X-App-Name: Logiciel-Clinique]
        T3A[Tenant: Clinique A]
        T3D[Tenant: Dispensaire D]
        DB3A[(DB: clinique_a)]
        DB3D[(DB: dispensaire_d)]
        
        A3 --> T3A
        A3 --> T3D
        T3A --> DB3A
        T3D --> DB3D
    end
    
    MS[Microservice Onboarding<br/>Akasi]
    
    A1 -.->|POST avec X-App-Name| MS
    A2 -.->|POST avec X-App-Name| MS
    A3 -.->|POST avec X-App-Name| MS
    
    style MS fill:#4CAF50
    style A1 fill:#2196F3
    style A2 fill:#FF9800
    style A3 fill:#9C27B0
```

**Règles d'Isolation** :

✅ **AUTORISÉ** :
- `SIH-Gabon` peut créer "Clinique A"
- `ERP-Sante` peut AUSSI créer "Clinique A" (pas de conflit)
- `Logiciel-Clinique` peut AUSSI créer "Clinique A" (pas de conflit)

❌ **INTERDIT** :
- `SIH-Gabon` ne peut PAS créer "Clinique A" deux fois
- `ERP-Sante` ne peut PAS créer "Clinique A" deux fois

**Pourquoi c'est important** :
- Chaque application a son propre espace de noms
- Pas de collision entre différentes applications
- Permet la réutilisation du microservice par plusieurs clients

---

## 🔐 Schéma 4 : Flux d'Authentification et Sécurité

```mermaid
graph LR
    subgraph "Requête Cliente"
        REQ[Requête HTTP]
        H1[Header: X-API-Key]
        H2[Header: X-App-Name]
        BODY[Body: JSON]
    end
    
    subgraph "Validation Microservice"
        V1{API Key<br/>valide?}
        V2{X-App-Name<br/>présent?}
        V3{Organisation<br/>unique pour<br/>cette app?}
    end
    
    subgraph "Traitement"
        PROC[Création Tenant]
        SUCCESS[Succès 200]
        ERROR[Erreur 4xx]
    end
    
    REQ --> V1
    H1 --> V1
    H2 --> V2
    BODY --> V3
    
    V1 -->|Non| ERROR
    V1 -->|Oui| V2
    V2 -->|Non| ERROR
    V2 -->|Oui| V3
    V3 -->|Existe déjà| ERROR
    V3 -->|Unique| PROC
    PROC --> SUCCESS
    
    style V1 fill:#FFC107
    style V2 fill:#FFC107
    style V3 fill:#FFC107
    style SUCCESS fill:#4CAF50
    style ERROR fill:#F44336
```

---

## 📊 Schéma 5 : Structure des Données

```mermaid
erDiagram
    ONBOARDING_SESSIONS ||--o{ AUTO_LOGIN_TOKENS : "génère"
    ONBOARDING_SESSIONS {
        int id PK
        string session_id
        string organization_name
        string source_app_name
        string subdomain
        string database_name
        string admin_email
        string status
        timestamp created_at
    }
    
    AUTO_LOGIN_TOKENS {
        int id PK
        string token
        int user_id
        string subdomain
        string database_name
        timestamp expires_at
    }
    
    TENANT_DATABASES ||--o{ USERS : "contient"
    TENANT_DATABASES ||--o{ CUSTOM_TABLES : "contient"
    
    TENANT_DATABASES {
        string name
        string subdomain
    }
    
    USERS {
        int id PK
        string name
        string email
        string password
        timestamp created_at
    }
    
    CUSTOM_TABLES {
        string table_name
        text schema
    }
```

**Légende** :
- **Base Centrale (MySQL)** : Contient `onboarding_sessions` et `auto_login_tokens`
- **Bases Tenant** : Chaque tenant a sa propre base avec `users` et tables personnalisées

---

## 🎯 Schéma 6 : Cas d'Usage Typique

```mermaid
journey
    title Parcours d'Intégration d'un Nouveau Client
    section Développeur App Cliente
      Obtenir clé API: 5: Dev
      Lire documentation: 4: Dev
      Préparer migrations SQL: 3: Dev
      Implémenter appel API: 4: Dev
      Tester en local: 3: Dev
    section Microservice
      Valider requête: 5: Microservice
      Créer tenant: 5: Microservice
      Exécuter migrations: 4: Microservice
      Envoyer callback: 5: Microservice
    section Utilisateur Final
      Recevoir email: 5: User
      Cliquer activation: 5: User
      Accéder dashboard: 5: User
      Utiliser application: 5: User
```

---

## 📝 Notes d'Utilisation

### Comment visualiser ces diagrammes ?

1. **Sur GitHub/GitLab** : Les diagrammes Mermaid s'affichent automatiquement
2. **VS Code** : Installer l'extension "Markdown Preview Mermaid Support"
3. **En ligne** : Copier le code Mermaid sur https://mermaid.live/

### Partager avec vos collègues

Vous pouvez :
- Exporter ce fichier en PDF (les diagrammes seront inclus)
- Partager le fichier Markdown sur votre dépôt Git
- Copier les diagrammes individuellement dans vos présentations

---

© 2026 Akasi Group - Documentation Technique
