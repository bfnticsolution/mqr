-- ============================================
-- MENU QR - Base de données complète
-- NTIC Solution
-- Version internationale - 20 Mai 2026
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Utiliser la base existante
USE `if0_41931124_menu_qr`;

-- ============================================
-- Table `restaurants`
-- ============================================
CREATE TABLE IF NOT EXISTS `restaurants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `telephone` VARCHAR(20) NOT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `nom_restaurant` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `adresse` VARCHAR(255) DEFAULT NULL,
  `ville` VARCHAR(100) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `heure_ouverture` TIME DEFAULT '07:00:00',
  `heure_fermeture` TIME DEFAULT '22:00:00',
  `jours_ouverture` VARCHAR(100) DEFAULT 'Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
  `telephone_whatsapp` VARCHAR(20) DEFAULT NULL,
  `module` ENUM('simple','pro') NOT NULL DEFAULT 'simple',
  `date_expiration_module` DATETIME DEFAULT NULL,
  `statut` ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  `qr_code_path` VARCHAR(255) DEFAULT NULL,
  `nb_scans` INT(11) NOT NULL DEFAULT 0,
  `question_secrete` VARCHAR(255) DEFAULT NULL,
  `reponse_secrete` VARCHAR(255) DEFAULT NULL,
  `reset_token` VARCHAR(64) DEFAULT NULL,
  `reset_token_expire` DATETIME DEFAULT NULL,
  `date_inscription` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `categories`
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT(11) NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `icone` VARCHAR(50) DEFAULT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `plats`
-- ============================================
CREATE TABLE IF NOT EXISTS `plats` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT(11) NOT NULL,
  `categorie_id` INT(11) DEFAULT NULL,
  `nom` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `prix` INT(11) NOT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `badge` ENUM('aucun','epice','vegetarien','best_seller') DEFAULT 'aucun',
  `disponible` TINYINT(1) NOT NULL DEFAULT 1,
  `nb_vues` INT(11) NOT NULL DEFAULT 0,
  `nb_commandes` INT(11) NOT NULL DEFAULT 0,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`),
  KEY `categorie_id` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `commandes`
-- ============================================
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code_suivi` VARCHAR(10) DEFAULT NULL,
  `restaurant_id` INT(11) NOT NULL,
  `numero_table` VARCHAR(10) DEFAULT NULL,
  `nom_client` VARCHAR(150) DEFAULT NULL,
  `telephone_client` VARCHAR(20) DEFAULT NULL,
  `serveur` VARCHAR(150) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `mode_commande` ENUM('sur_place','emporter') DEFAULT 'sur_place',
  `total` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('en_attente','confirmee','en_preparation','prete','livree','annulee') NOT NULL DEFAULT 'en_attente',
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_suivi` (`code_suivi`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `commande_plats`
-- ============================================
CREATE TABLE IF NOT EXISTS `commande_plats` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `commande_id` INT(11) NOT NULL,
  `plat_id` INT(11) NOT NULL,
  `quantite` INT(11) NOT NULL DEFAULT 1,
  `prix_unitaire` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `plat_id` (`plat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `suivi_commandes`
-- ============================================
CREATE TABLE IF NOT EXISTS `suivi_commandes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id` INT NOT NULL,
  `statut` VARCHAR(30) NOT NULL,
  `commentaire` TEXT DEFAULT NULL,
  `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table `paiements`
-- ============================================
CREATE TABLE IF NOT EXISTS `paiements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT(11) NOT NULL,
  `reference` VARCHAR(100) NOT NULL,
  `chariow_sale_id` VARCHAR(50) DEFAULT NULL,
  `montant` INT(11) NOT NULL,
  `module` VARCHAR(20) NOT NULL,
  `statut` VARCHAR(20) NOT NULL DEFAULT 'initie',
  `date_paiement` DATETIME DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `otp`
-- ============================================
CREATE TABLE IF NOT EXISTS `otp` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `telephone` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `action` VARCHAR(20) NOT NULL DEFAULT 'inscription',
  `tentatives` INT(11) NOT NULL DEFAULT 0,
  `valide` TINYINT(1) NOT NULL DEFAULT 1,
  `date_expiration` DATETIME NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `tables_qr`
-- ============================================
CREATE TABLE IF NOT EXISTS `tables_qr` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `restaurant_id` INT NOT NULL,
  `numero_table` VARCHAR(10) NOT NULL,
  `qr_code_path` VARCHAR(255) DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table `visites_menu`
-- ============================================
CREATE TABLE IF NOT EXISTS `visites_menu` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT(11) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `date_visite` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table `visites_plat`
-- ============================================
CREATE TABLE IF NOT EXISTS `visites_plat` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `plat_id` INT(11) NOT NULL,
  `restaurant_id` INT(11) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `date_visite` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `plat_id` (`plat_id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;