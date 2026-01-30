# Configuration du Dashboard par Tenant

## Vue d'ensemble

Cette fonctionnalité permet à chaque tenant de personnaliser son dashboard avec des widgets modulaires, des thèmes et des préférences. Chaque utilisateur peut configurer son propre aperçu unique du dashboard.

## Architecture

### 1. Modèle de données

Le modèle `ConfigurationDashboard` stocke les préférences de chaque utilisateur :
- **theme** : Thème d'affichage (light, dark, auto)
- **langue** : Langue de l'interface (fr, en, es)
- **widgets_config** : Configuration JSON des widgets activés
- **preferences** : Autres préférences utilisateur

### 2. Widgets disponibles

Les widgets suivants sont disponibles :

1. **Welcome** (👋) : Message de bienvenue personnalisé
2. **Tenant Info** (🏢) : Informations de l'organisation
3. **User Info** (👤) : Informations de l'utilisateur connecté
4. **Stats** (📊) : Statistiques générales
5. **Quick Actions** (⚡) : Actions rapides fréquemment utilisées
6. **Recent Activity** (🕐) : Activité récente

### 3. Tailles de widgets

Chaque widget peut avoir trois tailles :
- **small** : 1 colonne
- **medium** : 1 colonne (par défaut)
- **large** : 2 colonnes

### 4. Contrôleurs

#### DashboardController
- Charge la configuration du dashboard pour l'utilisateur connecté
- Passe les widgets configurés à la vue
- Utilise une configuration par défaut si aucun widget n'est configuré

#### DashboardConfigController
- `index()` : Affiche la page de configuration
- `store()` : Sauvegarde la configuration
- `updateTheme()` : Met à jour uniquement le thème (API)

### 5. Routes

```php
// Configuration du dashboard
GET  /dashboard/config          -> dashboard.config
POST /dashboard/config          -> dashboard.config.store
POST /dashboard/config/theme    -> dashboard.config.theme
```

### 6. Vues

#### Dashboard principal (`dashboard.blade.php`)
- Affiche les widgets configurés dynamiquement
- Support des thèmes (light/dark)
- Layout responsive avec grid CSS

#### Page de configuration (`dashboard/config.blade.php`)
- Interface pour activer/désactiver les widgets
- Sélection de la taille de chaque widget
- Configuration du thème et de la langue
- Toggle switches pour une meilleure UX

#### Widgets (`dashboard/widgets/*.blade.php`)
- Chaque widget est un partial Blade indépendant
- Reçoit les paramètres : `$size`, `$tenant`, `$settings`
- Peut être facilement étendu

## Utilisation

### Pour l'utilisateur

1. Accéder à la configuration : Cliquer sur "⚙️ Configurer" dans le header du dashboard
2. Choisir le thème : Sélectionner light, dark ou auto
3. Choisir la langue : Sélectionner fr, en ou es
4. Activer les widgets : Utiliser les toggle switches pour activer/désactiver
5. Ajuster les tailles : Sélectionner la taille pour chaque widget
6. Sauvegarder : Cliquer sur "Enregistrer la configuration"

### Pour le développeur

#### Ajouter un nouveau widget

1. Créer le fichier widget dans `resources/views/dashboard/widgets/` :
```blade
{{-- resources/views/dashboard/widgets/mon_widget.blade.php --}}
<div class="widget widget-mon-widget widget-{{ $size ?? 'medium' }}">
    <div class="details-section">
        <h3>Mon Widget</h3>
        {{-- Contenu du widget --}}
    </div>
</div>
```

2. Ajouter le widget dans `DashboardConfigController::getAvailableWidgets()` :
```php
[
    'id' => 'mon_widget',
    'name' => 'Mon Widget',
    'description' => 'Description du widget',
    'icon' => '🎯',
    'default_size' => 'medium',
]
```

3. Ajouter le cas dans `dashboard.blade.php` :
```blade
@case('mon_widget')
    @include('dashboard.widgets.mon_widget', ['size' => $widgetSize, 'tenant' => $tenant ?? null])
    @break
```

#### Personnaliser un widget existant

Modifier directement le fichier dans `resources/views/dashboard/widgets/`. Les widgets reçoivent :
- `$size` : Taille du widget (small, medium, large)
- `$tenant` : Objet Tenant (si disponible)
- `$settings` : Paramètres personnalisés du widget

## Structure des données

### Configuration JSON des widgets

```json
[
    {
        "id": "welcome",
        "position": 0,
        "size": "large",
        "settings": {}
    },
    {
        "id": "stats",
        "position": 1,
        "size": "medium",
        "settings": {}
    }
]
```

### Préférences utilisateur

```json
{
    "notifications": true,
    "email_alerts": false,
    "custom_setting": "value"
}
```

## Thèmes

### Light (par défaut)
- Fond : #f5f7fa
- Cartes : #ffffff
- Texte : #333333

### Dark
- Fond : #1a1a1a
- Cartes : #2a2a2a
- Texte : #e0e0e0

### Auto
- Détecte automatiquement les préférences système

## Responsive Design

Le dashboard s'adapte automatiquement :
- **Desktop** : Grid avec colonnes multiples
- **Tablet** : Grid avec colonnes réduites
- **Mobile** : Une seule colonne, widgets empilés

## Sécurité

- Toutes les routes sont protégées par le middleware `auth`
- Chaque utilisateur ne peut configurer que son propre dashboard
- Validation des données côté serveur
- Protection CSRF sur tous les formulaires

## Améliorations futures

- [ ] Drag & drop pour réorganiser les widgets
- [ ] Widgets personnalisables par l'utilisateur
- [ ] Plus de widgets (graphiques, calendrier, etc.)
- [ ] Export/Import de configuration
- [ ] Templates de dashboard prédéfinis
- [ ] Widgets dynamiques avec données en temps réel

