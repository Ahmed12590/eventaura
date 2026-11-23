-- =========================================================
--  EVENTAURA — Base de données (MySQL / MariaDB)
--  Importer ce fichier dans phpMyAdmin :
--  1. Créer une base (ex : eventaura) en utf8mb4_unicode_ci
--  2. Onglet "Importer" → choisir ce fichier → Exécuter
-- =========================================================

SET NAMES utf8mb4;

-- Membres inscrits via le formulaire du site
CREATE TABLE IF NOT EXISTS members (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  prenom        VARCHAR(80)  NOT NULL,
  nom           VARCHAR(80)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  telephone     VARCHAR(20)  NOT NULL,
  cne           VARCHAR(20)  NULL,
  filiere       VARCHAR(120) NOT NULL,
  annee         VARCHAR(30)  NOT NULL,
  pole          VARCHAR(40)  NOT NULL,
  interets      VARCHAR(255) NULL,
  motivation    TEXT         NULL,
  password_hash VARCHAR(255) NOT NULL,
  statut        ENUM('actif','suspendu') NOT NULL DEFAULT 'actif',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    DATETIME     NULL,
  login_count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_members_email (email),
  KEY idx_members_pole (pole),
  KEY idx_members_last_login (last_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique de chaque connexion d'un membre
CREATE TABLE IF NOT EXISTS login_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id  INT UNSIGNED NOT NULL,
  logged_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip         VARCHAR(45)  NULL,
  user_agent VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_logs_member (member_id),
  KEY idx_logs_date (logged_at),
  CONSTRAINT fk_logs_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrateurs (accès au tableau de bord)
-- Le premier compte se crée via /admin/setup.php
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom           VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protection contre les tentatives de connexion répétées
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45)  NOT NULL,
  scope        VARCHAR(10)  NOT NULL,
  email        VARCHAR(190) NULL,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts (ip, scope, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
