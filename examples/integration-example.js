/**
 * Exemple d'intégration du microservice d'onboarding Akasi Group
 * 
 * Ce fichier montre comment intégrer le microservice dans votre application
 */

// Configuration
const ONBOARDING_API_URL = 'https://onboarding.akasigroup.com/api';
const API_KEY = 'YOUR_API_KEY_HERE'; // À remplacer par votre clé API

/**
 * Créer un nouveau tenant via l'API
 * 
 * @param {Object} hospitalData - Données de l'hôpital
 * @param {Object} adminData - Données de l'administrateur
 * @param {Object} options - Options supplémentaires
 * @returns {Promise<Object>} Résultat de l'onboarding
 */
async function createOnboarding(hospitalData, adminData, options = {}) {
  try {
    const response = await fetch(`${ONBOARDING_API_URL}/onboarding/create`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${API_KEY}`
      },
      body: JSON.stringify({
        hospital: {
          name: hospitalData.name,
          address: hospitalData.address || null,
          phone: hospitalData.phone || null,
          email: hospitalData.email || null
        },
        admin: {
          first_name: adminData.firstName,
          last_name: adminData.lastName,
          email: adminData.email,
          password: adminData.password
        },
        options: {
          send_welcome_email: options.sendWelcomeEmail !== false,
          auto_login: options.autoLogin !== false
        }
      })
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || 'Erreur lors de la création de l\'onboarding');
    }

    if (!result.success) {
      throw new Error(result.message || 'Échec de la création de l\'onboarding');
    }

    return result.data;
  } catch (error) {
    console.error('Erreur création onboarding:', error);
    throw error;
  }
}

/**
 * Vérifier le statut d'un onboarding
 * 
 * @param {string} subdomain - Le sous-domaine du tenant
 * @returns {Promise<Object>} Statut de l'onboarding
 */
async function getOnboardingStatus(subdomain) {
  try {
    const response = await fetch(`${ONBOARDING_API_URL}/onboarding/status/${subdomain}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${API_KEY}`
      }
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || 'Erreur lors de la récupération du statut');
    }

    return result.data;
  } catch (error) {
    console.error('Erreur récupération statut:', error);
    throw error;
  }
}

/**
 * Obtenir les informations d'un tenant
 * 
 * @param {string} subdomain - Le sous-domaine du tenant
 * @returns {Promise<Object>} Informations du tenant
 */
async function getTenantInfo(subdomain) {
  try {
    const response = await fetch(`${ONBOARDING_API_URL}/tenant/${subdomain}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${API_KEY}`
      }
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message || 'Erreur lors de la récupération du tenant');
    }

    return result.data;
  } catch (error) {
    console.error('Erreur récupération tenant:', error);
    throw error;
  }
}

// Exemple d'utilisation
async function example() {
  try {
    // Créer un nouveau tenant
    const result = await createOnboarding(
      {
        name: 'Hôpital Central',
        address: '123 Rue de la Santé, Paris',
        phone: '+33 1 23 45 67 89',
        email: 'contact@hopital-central.fr'
      },
      {
        firstName: 'Jean',
        lastName: 'Dupont',
        email: 'admin@hopital-central.fr',
        password: 'SecurePassword123!'
      },
      {
        sendWelcomeEmail: true,
        autoLogin: true
      }
    );

    console.log('Onboarding créé avec succès:', result);
    console.log('Subdomain:', result.subdomain);
    console.log('URL:', result.url);

    // Rediriger vers le dashboard du tenant
    window.location.href = result.url;

    // Ou vérifier le statut plus tard
    const status = await getOnboardingStatus(result.subdomain);
    console.log('Statut:', status);

    // Ou obtenir les informations complètes du tenant
    const tenantInfo = await getTenantInfo(result.subdomain);
    console.log('Informations tenant:', tenantInfo);

  } catch (error) {
    console.error('Erreur:', error.message);
    // Afficher un message d'erreur à l'utilisateur
    alert('Erreur lors de la création de votre compte: ' + error.message);
  }
}

// Export pour utilisation dans d'autres modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    createOnboarding,
    getOnboardingStatus,
    getTenantInfo
  };
}
