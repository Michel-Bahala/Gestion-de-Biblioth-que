# 📚 BiblioTech Pro — Système de Gestion de Bibliothèque Modern UI

BiblioTech Pro est une application web de gestion de bibliothèque combinant une logique métier complète (CRUD avancé + gestion des flux d'emprunts) et une interface utilisateur haut de gamme (Dark Mode) inspirée des meilleurs designs de **Figma Community**.

L'application automatise l'initialisation de son infrastructure de données (base de données et tables) dès son premier lancement, offrant une expérience clé en main.

---

## ✨ Fonctionnalités Clés

### 🖥️ Interface Utilisateur (Design Figma Pro)
- **Architecture asymétrique** avec une barre latérale fixe pour le catalogage et un tableau de bord dynamique à droite.
- **Typographie premium** basée sur la police *Plus Jakarta Sans*.
- **Composants d'affichage modernes** : Cartes de statistiques en temps réel, fenêtres modales fluides pour les sorties de volumes et badges de statut colorés.

### ⚙️ Logique Métier & CRUD Avancé
- **Catalogue Complet (Livres)** : Ajouter, modifier, rechercher et supprimer des ouvrages en rayon.
- **Gestion Logistique des Flux d'Emprunt** :
  - Enregistrement des emprunteurs (Nom, coordonnées téléphoniques).
  - Choix de la durée de l'accord (7, 14 ou 30 jours) avec calcul automatique de la date de retour prévue.
  - **Détection intelligente des retards** : Le système compare la date serveur en temps réel et applique un statut "En Retard" si l'échéance est dépassée.
  - Retour de volume en un seul clic pour réajuster le stock instantanément.

---

## 🛠️ Stack Technique

- **Backend :** PHP 8.2+ (Programmation procédurale propre, architecture basée sur des fonctions réutilisables).
- **Base de données :** MySQL / MariaDB (Interfacé de manière sécurisée via l'API **PDO** avec requêtes préparées).
- **Frontend :** HTML5, CSS3 étendu, Bootstrap 5.3 (Thème personnalisé) & Bootstrap Icons.

---

## 🚀 Installation Locale (Déploiement en 3 étapes)

### 1. Prérequis & Fichier Hosts
Assurez-vous d'utiliser un serveur local comme **XAMPP** ou **Laragon**. 

Pour faire tourner le projet sur le domaine local personnalisé `tpphp.local`, ouvrez votre fichier `C:\Windows\System32\drivers\etc\hosts` en mode administrateur et ajoutez-y la configuration suivante :
```text
127.0.0.1       tpphp.local
127.0.0.1       localhost
::1             tpphp.local
::1             localhost
