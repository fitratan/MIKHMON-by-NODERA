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

<p align="center">
  <a href="https://github.com/fitratan/MIKHMON-by-NODERA"><img src="https://komarev.com/ghpvc/?username=fitratan-mikhmon-by-nodera&label=Repository%20Views&color=0052cc&style=for-the-badge" alt="Repository Views"></a>
  <a href="https://github.com/fitratan/MIKHMON-by-NODERA/releases"><img src="https://img.shields.io/github/downloads/fitratan/MIKHMON-by-NODERA/total.svg?style=for-the-badge&logo=github&color=00B074" alt="Downloads"></a>
  <a href="https://github.com/fitratan/MIKHMON-by-NODERA/stargazers"><img src="https://img.shields.io/github/stars/fitratan/MIKHMON-by-NODERA?style=for-the-badge&logo=github&color=E3B341" alt="Stars"></a>
  <a href="https://github.com/fitratan/MIKHMON-by-NODERA/network/members"><img src="https://img.shields.io/github/forks/fitratan/MIKHMON-by-NODERA?style=for-the-badge&logo=github&color=777BB4" alt="Forks"></a>
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
   - Téléchargez l'archive ZIP officielle depuis GitHub Releases : **[Télécharger Mikhmon-Desktop.zip](https://github.com/fitratan/MIKHMON-by-NODERA/releases/latest/download/Mikhmon-Desktop.zip)** *(ou consultez la page [GitHub Releases](https://github.com/fitratan/MIKHMON-by-NODERA/releases))*
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

### C. Déploiement sur MikroTik RouterOS v7 (Conteneur Docker Natif)

MIKHMON by NODERA peut fonctionner directement 24h/24 et 7j/7 au sein de votre routeur MikroTik grâce à la fonctionnalité officielle **Container (Docker)** de RouterOS v7, sans avoir besoin d'un ordinateur ou d'un serveur tiers allumé en continu.

#### 📌 Prérequis Matériels & RouterOS :
1. **RouterOS** : Version `7.4` ou supérieure avec le package `container` installé.
2. **Architecture Processeur** : Compatible **x86/CHR** (PC Routeur), **ARM** (hAP ac3, hAP ax2/ax3, cAP ax, L009), ou **ARM64** (RB5009, CCR2004, CCR2116). *(Remarque : Les architectures MIPSBE/SMIPS comme le RB750Gr3 ne prennent pas en charge les conteneurs)*.
3. **Stockage** :
   - **PC x86 / Cloud CHR** : Utilise le disque dur / SSD interne du routeur (aucun support USB externe nécessaire).
   - **Routeur Physique RouterBOARD (RB / hAP / CCR)** : Fortement recommandé d'utiliser une clé USB / SSD externe (formaté en `ext4` via *System > Disks*, par ex. nommé `usb1`) pour préserver l'endurance de la mémoire flash interne.

---

#### ⚡ Option Rapide : Installateur 1-Clic Complet (Inclus Watchdog & Auto-Démarrage)

Pour un déploiement entièrement automatique de A à Z (Bridge, VETH, NAT, Dossier Mount, Téléchargement d'Image, Redirection Port, Démarrage Automatique au boot et Watchdog 24/7) :

* **Pour PC Routeur x86 / Cloud CHR :**
  ```routeros
  /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/scripts/install-container-x86.rsc" dst-path=install.rsc; /import install.rsc
  ```

* **Pour Routeur Physique RouterBOARD (Clé USB / SSD `usb1`) :**
  ```routeros
  /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/scripts/install-container-usb.rsc" dst-path=install.rsc; /import install.rsc
  ```

---

#### 🚀 Étapes d'Installation Manuelle (Terminal MikroTik CLI) :

##### 1. Activer le Mode Conteneur sur MikroTik :
```routeros
/system device-mode update container=yes
```
> **Confirmation Physique** : Sur un routeur physique, MikroTik demandera d'appuyer sur le *bouton Reset physique* ou de redémarrer électriquement l'appareil dans un délai de 5 minutes pour confirmer la sécurité. Sur CHR / PC x86, l'activation est immédiate.

##### 2. Configurer le Réseau Virtuel (VETH) & le Bridge du Conteneur :
```routeros
# Créer un bridge dédié au conteneur
/interface bridge add name=dockers comment="Bridge Conteneur Mikhmon"
/ip address add address=172.17.0.1/24 interface=dockers

# Créer l'interface Ethernet Virtuelle (VETH)
/interface veth add name=veth-mikhmon address=172.17.0.2/24 gateway=172.17.0.1
/interface bridge port add bridge=dockers interface=veth-mikhmon

# Activer le Masquerade NAT pour que le conteneur accède à Internet (licence cloud, passerelles SMS/WA/Telegram)
/ip firewall nat add chain=srcnat action=masquerade src-address=172.17.0.0/24 comment="Masquerade Conteneur Mikhmon"
```

##### 3. Créer le Dossier de Montage (Pour Conserver la Licence & les Configurations Persistantes) :
* **Pour PC Routeur x86 / CHR (Stockage SSD Interne) :**
  ```routeros
  /container mounts add list=mikhmon_config src=mikhmon/config dst=/app/mikhmon/config
  ```
* **Pour Routeur Physique RouterBOARD (Via Clé USB `usb1`) :**
  ```routeros
  /container mounts add list=mikhmon_config src=usb1/mikhmon/config dst=/app/mikhmon/config
  ```
> *(Remarque : Sur les anciennes versions de RouterOS v7 antérieures à v7.14, si une erreur de paramètre invalide apparaît sur `list=`, utilisez `name=mikhmon_config`).*

##### 4. Télécharger l'Image Officielle Directement depuis GitHub :
Utilisez la commande officielle `/tool fetch` depuis GitHub (sans contrainte de délai d'expiration de registre) :
* **Pour PC Routeur x86 / CHR :**
  ```routeros
  /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/container-release/mikhmon-x86.tar.gz" dst-path="mikhmon.tar.gz" mode=https
  ```
* **Pour Routeur Physique RouterBOARD (Enregistrer sur USB `usb1`) :**
  ```routeros
  /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/container-release/mikhmon-x86.tar.gz" dst-path="usb1/mikhmon.tar.gz" mode=https
  ```
*(Patientez pendant le téléchargement dans le terminal jusqu'à ce que le statut indique `finished`)*.

##### 5. Installer & Démarrer le Conteneur Mikhmon :
* **Pour PC Routeur x86 / CHR :**
  ```routeros
  /container add file=mikhmon.tar.gz interface=veth-mikhmon root-dir=mikhmon mountlists=mikhmon_config logging=yes start-on-boot=yes
  ```
* **Pour Routeur Physique RouterBOARD (USB `usb1`) :**
  ```routeros
  /container add file=usb1/mikhmon.tar.gz interface=veth-mikhmon root-dir=usb1/mikhmon mountlists=mikhmon_config logging=yes start-on-boot=yes
  ```

Patientez quelques secondes pendant l'extraction, puis vérifiez le statut :
```routeros
/container print
```
Dès que le statut passe à **`S` (STOPPED)**, démarrez le conteneur :
```routeros
/container start [find interface=veth-mikhmon]
```
Le statut passera à **`R` (RUNNING)** ! 🎉

##### 6. Configuration du Démarrage Automatique (Auto-Start & Watchdog 24/7) :
> **⚠️ IMPORTANT** : Par défaut, le conteneur MikroTik peut rester au statut `STOPPED` après un redémarrage du routeur ou une coupure de courant (particulièrement sur les routeurs RouterBOARD physiques qui mettent 5 à 15 secondes pour monter le disque USB `usb1`).
>
> Configurez ce qui suit dans le Terminal MikroTik pour que Mikhmon **démarre automatiquement à chaque redémarrage** et reste actif 24h/24 :

###### Option A : Commande Terminal 1-Ligne (Recommandé Rapide)
Exécutez cette commande dans le Terminal WinBox pour télécharger et appliquer directement le script officiel :
```routeros
/tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/scripts/autostart-mikhmon.rsc" dst-path=autostart.rsc; /import autostart.rsc
```

###### Option B : Copier & Coller Manuellement dans le Terminal
Ou collez le script complet suivant directement dans New Terminal :
```routeros
# 1. Activer la propriété start-on-boot sur le conteneur
/container set [find interface=veth-mikhmon] start-on-boot=yes

# 2. Supprimer les anciens planificateurs s'ils existent
/system scheduler remove [find name="AutoStart-Mikhmon"]
/system scheduler remove [find name="Watchdog-Mikhmon"]

# 3. Planificateur Auto-Start au démarrage (avec délai de 15s pour le montage complet du disque)
/system scheduler add name="AutoStart-Mikhmon" start-time=startup interval=0s \
    on-event=":delay 15s; :log info \"[Mikhmon AutoStart] Demarrage du conteneur post-boot...\"; /container start [find interface=veth-mikhmon];" \
    comment="Auto-start Conteneur Mikhmon au demarrage (NODERA)"

# 4. Planificateur Watchdog 24/7 (verifie chaque 1 min et relance le conteneur s'il est arrete)
/system scheduler add name="Watchdog-Mikhmon" interval=1m \
    on-event=":if ([:len [/container find interface=veth-mikhmon status=running]] = 0) do={ :log warning \"[Mikhmon Watchdog] Conteneur hors ligne ! Relance en cours...\"; /container start [find interface=veth-mikhmon]; }" \
    comment="Watchdog pour maintenir le Conteneur Mikhmon 24/7 (NODERA)"

# 5. Démarrer le conteneur maintenant s'il est actuellement arrêté
:if ([:len [/container find interface=veth-mikhmon status=running]] = 0) do={
    /container start [find interface=veth-mikhmon];
}
```
*(Le fichier script prêt à l'emploi est également disponible sur : [`scripts/autostart-mikhmon.rsc`](scripts/autostart-mikhmon.rsc))*.

##### 7. Créer la Redirection de Port (DST-NAT) pour l'Accès Web :
Pour accéder à Mikhmon depuis votre smartphone ou PC sur le réseau local via le port `8082` :
```routeros
/ip firewall nat add chain=dstnat dst-port=8082 protocol=tcp action=dst-nat to-addresses=172.17.0.2 to-ports=8080 comment="Acces Web Mikhmon NODERA"
```

Accédez à Mikhmon via votre navigateur :
👉 **`http://IP-ROUTEUR-MIKROTIK:8082`** *(Exemple : `http://192.168.88.1:8082`)*

---

#### 🔑 Connexion au Routeur & Activation de la Licence :
1. **Connexion API MikroTik** :
   - Dans les paramètres du routeur sur Mikhmon, renseignez l'adresse IP Passerelle du conteneur : **`172.17.0.1`** (Port API : `8728`).
   - Assurez-vous que le service API est activé sur MikroTik (`/ip service enable api`).
2. **Activation de la Licence & HWID** :
   - Sur la page de connexion de Mikhmon, le **HWID de ce conteneur** sera automatiquement détecté et conservé dans le dossier `config/.hwid`.
   - Identifiants initiaux : utilisateur `nodera`, mot de passe `nodera`.
   - Bénéficiez immédiatement de **7 Jours d'Essai Gratuit**, ou activez votre clé officielle achetée sur [panel.dgtlnetsolution.com](https://panel.dgtlnetsolution.com).

---

#### 🔄 Comment Mettre à Jour Mikhmon dans le Conteneur :

Mikhmon exécuté dans un conteneur Docker MikroTik **peut être mis à jour très simplement** sans risque de perdre les sessions du routeur ou la licence :

##### Méthode 1 : Mise à Jour en 1-Clic depuis l'Interface Web Mikhmon (Le Plus Pratique)
1. Ouvrez Mikhmon dans votre navigateur (`http://IP-ROUTEUR:8082`).
2. Allez dans le menu **Mise à jour du Système** (icône d'actualisation ou URL `admin.php?id=update`).
3. Cliquez sur **"Vérifier les Mises à Jour"** pour récupérer la dernière version et les derniers commits depuis GitHub.
4. Cliquez sur **"Mettre à jour maintenant (1-Clic)"**. Le moteur PHP du conteneur téléchargera et appliquera immédiatement les derniers fichiers de code.
5. **Garantie Zéro Perte de Données** : Les fichiers de configuration (`include/config.php`), la licence & HWID (`config/`) et vos modèles de coupons personnalisés (`template/`) sont 100% préservés.

##### Méthode 2 : Mise à Jour de l'Image du Conteneur via Terminal MikroTik
Si une mise à jour majeure de l'image de base est publiée (ex. version de PHP) :
1. Arrêtez et supprimez l'ancien conteneur :
   ```routeros
   /container stop [find interface=veth-mikhmon]
   /container remove [find interface=veth-mikhmon]
   ```
2. Téléchargez la dernière image et recréez le conteneur :
   * **Option A : Pour PC Routeur x86 / Cloud CHR / Stockage Interne (Sans USB) :**
     ```routeros
     /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/container-release/mikhmon-x86.tar.gz" dst-path="mikhmon.tar.gz" mode=https
     /container add file=mikhmon.tar.gz interface=veth-mikhmon root-dir=mikhmon mountlists=mikhmon_config logging=yes start-on-boot=yes
     /container start [find interface=veth-mikhmon]
     ```
   * **Option B : Pour Routeur Physique RouterBOARD (Clé USB / SSD `usb1`) :**
     ```routeros
     /tool fetch url="https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/container-release/mikhmon-x86.tar.gz" dst-path="usb1/mikhmon.tar.gz" mode=https
     /container add file=usb1/mikhmon.tar.gz interface=veth-mikhmon root-dir=usb1/mikhmon mountlists=mikhmon_config logging=yes start-on-boot=yes
     /container start [find interface=veth-mikhmon]
     ```
   *(La licence et les réglages restent intacts car ils sont stockés sur le volume monté `mikhmon/config` ou `usb1/mikhmon/config`)*.

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

- **Système d'exploitation** : Windows 10 / Windows 11 (64-bit), Serveur Linux, ou MikroTik RouterOS v7.4+
- **Processeur** : Dual Core 1.5 GHz ou supérieur / Architecture MikroTik ARM, ARM64 ou x86
- **Mémoire RAM** : 2 Go minimum sur PC/Serveur, ou routeur MikroTik avec 512 Mo+ RAM (1 Go+ recommandé)
- **Espace disque** : 500 Mo d'espace libre (ou support externe USB/SSD sur MikroTik)
- **Connexion Internet** : Requise lors de l'activation initiale de la licence et des synchronisations gateway

---

<p align="center">
  <b>NODERA</b><br>
  Digital Network Solution<br>
  <i>Copyright &copy; 2026 NODERA. Tous droits réservés.</i>
</p>
