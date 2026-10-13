# MIKHMON par NODERA

<p align="center">
  <img src="assets/logo.png" alt="MIKHMON par NODERA" width="180"/>
</p>

<p align="center">
  <b>Langue / Language :</b>
  <a href="README.md"><b>🇮🇩 Bahasa Indonesia</b></a> |
  <a href="README.fr.md"><b>🇫🇷 Français (Côte d'Ivoire / Afrique)</b></a> |
  <a href="README.en.md"><b>🇬🇧 English</b></a>
</p>

<p align="center">
  <a href="https://panel.dgtlnetsolution.com/desktop-licenses"><img src="https://img.shields.io/badge/Plateforme-Windows%2010%20%7C%2011%20%7C%20Linux%20VPS-0052CC.svg?style=for-the-badge&logo=windows" alt="Plateforme"></a>
  <a href="https://panel.dgtlnetsolution.com"><img src="https://img.shields.io/badge/Moteur-PHP%208.2%20Micro--Serveur-777BB4.svg?style=for-the-badge&logo=php" alt="Moteur"></a>
  <a href="https://panel.dgtlnetsolution.com/desktop-licenses"><img src="https://img.shields.io/badge/Statut-Version%20Officielle-00B074.svg?style=for-the-badge" alt="Statut"></a>
  <a href="https://panel.dgtlnetsolution.com"><img src="https://img.shields.io/badge/S%C3%A9curit%C3%A9-Verrouillage%20HWID%20Cloud-E02424.svg?style=for-the-badge" alt="Sécurité"></a>
</p>

---

## À Propos de MIKHMON par NODERA

**MIKHMON par NODERA** est la nouvelle génération d'application de gestion de hotspots et de facturation MikroTik, développée et optimisée de manière moderne par **NODERA Team**.

Contrairement aux versions classiques de Mikhmon (v3/v4 legacy) qui ne servaient que d'interface API basique pour imprimer des tickets, **MIKHMON par NODERA** constitue un écosystème commercial complet : module de point de vente (POS) pour revendeurs et boutiques, paiements mobiles automatisés, passerelle WhatsApp multi-fournisseurs, notifications interactives Telegram, **Moteur FUP (Fair Usage Policy)**, **Surveillance du Trafic et des Interfaces en Temps Réel**, et environnement autonome portable sans nécessité d'installer XAMPP ou un serveur web tiers.

Cette version est entièrement compatible avec les réseaux déployés en **Côte d'Ivoire et en Afrique de l'Ouest**, avec prise en charge du français et des devises locales (FCFA).

---

## Comparatif : MIKHMON par NODERA vs Mikhmon Classique (v3/v4 Legacy)

Voici un tableau comparatif entre **MIKHMON par NODERA** et les versions traditionnelles de Mikhmon :

| Fonctionnalité / Paramètre | Mikhmon Classique (Legacy v3 / v4) | MIKHMON par NODERA (Desktop & VPS Autonome) |
| :--- | :--- | :--- |
| **Serveur & Exécution** | Nécessite d'installer un serveur web / XAMPP manuellement | **Micro-serveur portable (PHP 8.2)** intégré, lancement en 1 clic sans XAMPP |
| **Module Point de Vente (POS)** | Aucun | **Module Caisse & Revendeurs Partenaires** avec gestion de solde, dépôt et impression de reçus |
| **Paiement Automatique** | Aucun / Scripts tiers instables | **Paiements Automatisés & Portails Mobiles** avec génération instantanée de tickets payés |
| **Passerelle WhatsApp** | Aucune | **Passerelle WhatsApp Multi-Fournisseurs** pour envoi automatique des identifiants au client |
| **Notifications Telegram** | Alertes textuelles très limitées | **Bot Telegram Interactif** : alertes de vente, bilan du chiffre d'affaires, boutons d'action et alertes d'expiration |
| **Moteur FUP & Limitation** | Aucun / Manuel | **Moteur FUP** : configuration du seuil de quota, vitesse après FUP et cycle de réinitialisation |
| **Surveillance du Trafic** | Standard routeur basique | **Télémétrie Avancée des Interfaces** (Rx/Tx détaillé, trafic total en To, statut actif) |
| **Compatibilité RouterOS** | Limitée / Nombreuses erreurs sur RouterOS v7 | **Compatibilité totale RouterOS v6 & v7** avec optimisation des requêtes API |
| **Impression Thermique** | Format navigateur basique | **Impression Thermique Directe USB & Bluetooth (58mm/80mm)** avec mise en page professionnelle |
| **Licence & Protection** | Non sécurisée / Non gérée | **Licence Cloud Liée au HWID** : réinitialisation autonome du HWID via le portail en cas de changement de PC |
| **Support International** | Indonésien uniquement | **Support Multilingue (Français, Indonésien, Anglais)** avec support local Côte d'Ivoire |

---

## Fonctionnalités Principales

### 1. Module Caisse POS & Réseau de Revendeurs (Boutiques Partenaires)
- Confiez la vente de vos tickets Wi-Fi à des kiosques, boutiques de quartier ou revendeurs partenaires.
- Interface de caisse ultra-simplifiée et rapide, accessible depuis smartphone, tablette ou ordinateur.
- Gestion des soldes prépayés des revendeurs avec calcul automatique des marges bénéficiaires et impression instantanée des reçus.

### 2. Moteur FUP (Fair Usage Policy) & Limitation Automatique
- **Rôle & Utilité** : Empêche le monopole de la bande passante par les gros consommateurs (*heavy downloaders*). Le système suit précisément l'accumulation de données par profil hotspot.
- **Seuil de Quota (*Quota Threshold*)** : Définissez la limite de consommation équitable (ex. : 10 Go) avant application des restrictions.
- **Limitation Automatique (*Throttling*)** : Lorsque le quota FUP est atteint, le système réduit automatiquement la vitesse de connexion (ex. : 1 Mo/1 Mo) sans couper la session active, garantissant l'équité pour tous les utilisateurs.
- **Cycle de Réinitialisation** : Programmation flexible du reset des quotas (Manuel ou Automatique).

### 3. Télémétrie Avancée du Trafic & des Interfaces
- **Rôle & Utilité** : Surveillez la santé des liens réseau et la charge du routeur depuis un tableau de bord centralisé.
- **Détails Rx / Tx** : Suivi du volume cumulé de téléchargement (Rx) et de téléversement (Tx) en grande échelle (jusqu'à l'échelle des Téraoctets / To).
- **Statut des Interfaces** : Vérification en temps réel de l'état opérationnel de chaque interface physique ou virtuelle (`1_SFP_METRO`, `5_OM_BUDI`, `6_UPLINK_2`, etc.) pour détecter rapidement toute anomalie.

### 4. Passerelle WhatsApp Multi-Fournisseurs
- Envoi automatique du code ticket, du nom d'utilisateur, du mot de passe et de la durée de validité directement sur le WhatsApp du client dès la validation.
- Diffusion d'alertes d'expiration et d'informations sur l'état du réseau aux clients.

### 5. Bot Telegram Interactif & Alertes d'Expiration
- **Rapports de vente en direct** : notification instantanée à chaque ticket vendu.
- **Alertes d'expiration** : notification automatique dès qu'un ticket arrive à terme sur le MikroTik.
- **Bilan journalier** : synthèse automatique du chiffre d'affaires et statistiques quotidiennes.

### 6. Impression Thermique Standardisée & Modèles de Coupons
- Modèles de tickets parfaitement calibrés pour imprimantes thermiques **58 mm** et **80 mm**.
- Prise en charge de l'impression par lots (*batch print*) avec QR Code de connexion automatique (*Scan to Connect*).
- Personnalisation complète des styles graphiques et palettes de couleurs des coupons.

### 7. Architecture Multi-Routeurs (ROS v6 & ROS v7)
- Pilotez plusieurs routeurs MikroTik distants depuis une seule application centrale.
- Prise en charge des ports API standards et personnalisés avec chiffrement sécurisé.

### 8. Sécurité de Licence & Gestion Autonome du HWID
- Protection cryptographique basée sur l'identifiant matériel de la machine (HWID).
- **Réinitialisation autonome du HWID** : en cas de changement d'ordinateur de caisse, réinitialisez la liaison directement depuis votre espace client.

---

## Installation & Utilisation

### A. Installation sur Windows 10 / 11 (Desktop Standalone)
1. **Télécharger l'Application** :
   - Téléchargez l'archive ZIP officielle : **[Télécharger Mikhmon-Desktop.zip](https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip)**
   - Ou clonez le dépôt Git : `git clone https://github.com/fitratan/MIKHMON-by-NODERA.git`
2. **Extraire l'Archive** :
   - Extrayez le contenu dans un dossier de votre choix (ex : `C:\Mikhmon-Desktop`).
3. **Lancer l'Application** :
   - Ouvrez le dossier extrait et double-cliquez sur le fichier exécutable **`Mikhmon.exe`** (Application Desktop Autonome Tout-en-Un).
   - La fenêtre de l'application MIKHMON s'ouvre directement à l'écran (sans fenêtre de serveur séparée ni nécessité d'ouvrir un navigateur tiers).
   - Utilisez la barre d'accès rapide en haut à droite pour basculer entre le **Mode Admin Hotspot** (Ctrl+1) et le **Portail Kiosque / Revendeur** (Ctrl+2).
   - *(Optionnel)* `MikhmonServer.exe` reste disponible pour le mode serveur en arrière-plan avec barre des tâches.
4. **Première Connexion** :
   - Connectez-vous avec les identifiants par défaut : Nom d'utilisateur `nodera`, Mot de passe `nodera`.
5. **Essai Gratuit de 7 Jours (Automatique)** :
   - Toute nouvelle installation bénéficie d'un **Essai Gratuit de 7 Jours** sans clé de licence requise immédiatement. Toutes les fonctionnalités sont débloquées.

---

### B. Déploiement sur VPS Linux / Serveur Cloud (Ubuntu, Debian, AlmaLinux)

```bash
# 1. Accéder au répertoire web du serveur
cd /var/www/html

# 2. Télécharger et extraire le code source
wget https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip
unzip main.zip
mv MIKHMON-by-NODERA-main mikhmon
rm main.zip

# 3. Configurer les permissions des dossiers
chown -R www-data:www-data mikhmon
chmod -R 755 mikhmon
```
Accédez via votre navigateur : `http://IP-DE-VOTRE-VPS/mikhmon/login`

---

## Identifiants de Connexion par Défaut

| Paramètre | Valeur par Défaut | Description |
| :--- | :--- | :--- |
| **URL Locale** | `http://127.0.0.1:8080` | Port du micro-serveur local intégré PHP 8.2 |
| **Nom d'utilisateur** | `nodera` | Compte administrateur principal |
| **Mot de passe** | `nodera` | Mot de passe initial par défaut |

---

## Support Client & Activation de Licence

Les licences officielles **Mikhmon Desktop Standalone** peuvent être acquises et gérées via :

- **Portail Client NODERA** : [panel.dgtlnetsolution.com](https://panel.dgtlnetsolution.com)
- **Support WhatsApp Côte d'Ivoire** : **+255 07 88 22 26 99**
- **Support WhatsApp Indonésie** : **0851-5517-3547**
- **Telegram** : [@nullneko_exe](https://t.me/nullneko_exe)

---

## Configuration Système Requise

- **Système d'exploitation** : Windows 10 / Windows 11 (64-bit) ou Serveur Linux
- **Processeur** : Dual Core 1.5 GHz ou supérieur
- **Mémoire RAM** : 2 Go minimum (4 Go recommandés)
- **Espace disque** : 500 Mo d'espace libre
- **Connexion Internet** : Requise lors de l'activation initiale de la licence et des synchronisations gateway

---

<p align="center">
  <b>NODERA</b><br>
  Digital Network Solution<br>
  <i>Copyright &copy; 2026 NODERA. Tous droits réservés.</i>
</p>
