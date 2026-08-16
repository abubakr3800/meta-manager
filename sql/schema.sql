-- Meta (Facebook/Instagram) Manager — Database Schema
-- Short Circuit Company

CREATE DATABASE IF NOT EXISTS sc_meta_manager
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sc_meta_manager;

-- ---------------------------------------------------------------------
-- Admin users of this tool (multi-user)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','editor','viewer') NOT NULL DEFAULT 'editor',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- One row per Facebook user who has completed OAuth2.
-- Long-lived user access token is stored encrypted.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS meta_identities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT UNSIGNED NOT NULL,
  fb_user_id VARCHAR(64) NOT NULL,
  fb_name VARCHAR(190) DEFAULT NULL,
  access_token_enc TEXT NOT NULL,       -- encrypted long-lived user token
  token_expires_at DATETIME DEFAULT NULL,
  scopes VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_fbuser (admin_user_id, fb_user_id),
  CONSTRAINT fk_identity_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Facebook Pages the identity manages, each with its own Page Access Token
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS meta_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meta_identity_id INT UNSIGNED NOT NULL,
  fb_page_id VARCHAR(64) NOT NULL,
  page_name VARCHAR(190) DEFAULT NULL,
  page_access_token_enc TEXT NOT NULL,
  instagram_business_id VARCHAR(64) DEFAULT NULL,
  category VARCHAR(120) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_identity_page (meta_identity_id, fb_page_id),
  CONSTRAINT fk_page_identity FOREIGN KEY (meta_identity_id) REFERENCES meta_identities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Local cache/log of Page posts created/managed through this tool
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fb_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meta_page_id INT UNSIGNED NOT NULL,
  fb_post_id VARCHAR(64) DEFAULT NULL,   -- null until published (e.g. scheduled draft not yet sent)
  message TEXT DEFAULT NULL,
  link VARCHAR(500) DEFAULT NULL,
  image_url VARCHAR(500) DEFAULT NULL,
  status ENUM('draft','scheduled','published','deleted','failed') NOT NULL DEFAULT 'draft',
  scheduled_publish_time DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_post_page FOREIGN KEY (meta_page_id) REFERENCES meta_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Instagram media (feed posts) managed through this tool
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ig_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meta_page_id INT UNSIGNED NOT NULL,     -- linked page owning the IG business account
  ig_media_id VARCHAR(64) DEFAULT NULL,
  ig_container_id VARCHAR(64) DEFAULT NULL,
  caption TEXT DEFAULT NULL,
  media_url VARCHAR(500) DEFAULT NULL,
  media_type ENUM('IMAGE','VIDEO','CAROUSEL') NOT NULL DEFAULT 'IMAGE',
  status ENUM('container_created','published','deleted','failed') NOT NULL DEFAULT 'container_created',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_media_page FOREIGN KEY (meta_page_id) REFERENCES meta_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Ad accounts available to the identity
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ad_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meta_identity_id INT UNSIGNED NOT NULL,
  act_id VARCHAR(64) NOT NULL,           -- e.g. act_1234567890
  account_name VARCHAR(190) DEFAULT NULL,
  currency VARCHAR(10) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_identity_act (meta_identity_id, act_id),
  CONSTRAINT fk_adacct_identity FOREIGN KEY (meta_identity_id) REFERENCES meta_identities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Campaigns / Ad Sets / Ads (local cache mirroring Graph API objects)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ad_campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ad_account_id INT UNSIGNED NOT NULL,
  fb_campaign_id VARCHAR(64) DEFAULT NULL,
  name VARCHAR(190) NOT NULL,
  objective VARCHAR(60) NOT NULL,
  status ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'PAUSED',
  daily_budget_cents INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_campaign_account FOREIGN KEY (ad_account_id) REFERENCES ad_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ad_sets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ad_campaign_id INT UNSIGNED NOT NULL,
  fb_adset_id VARCHAR(64) DEFAULT NULL,
  name VARCHAR(190) NOT NULL,
  status ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'PAUSED',
  optimization_goal VARCHAR(60) DEFAULT NULL,
  billing_event VARCHAR(60) DEFAULT NULL,
  bid_amount_cents INT UNSIGNED DEFAULT NULL,
  targeting_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_adset_campaign FOREIGN KEY (ad_campaign_id) REFERENCES ad_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ad_set_id INT UNSIGNED NOT NULL,
  fb_ad_id VARCHAR(64) DEFAULT NULL,
  name VARCHAR(190) NOT NULL,
  status ENUM('ACTIVE','PAUSED','DELETED','ARCHIVED') NOT NULL DEFAULT 'PAUSED',
  creative_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ad_adset FOREIGN KEY (ad_set_id) REFERENCES ad_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Lead-gen forms and leads (leads are read-mostly / deletable for GDPR)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lead_forms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meta_page_id INT UNSIGNED NOT NULL,
  fb_form_id VARCHAR(64) NOT NULL,
  form_name VARCHAR(190) DEFAULT NULL,
  status VARCHAR(30) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_page_form (meta_page_id, fb_form_id),
  CONSTRAINT fk_form_page FOREIGN KEY (meta_page_id) REFERENCES meta_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_form_id INT UNSIGNED NOT NULL,
  fb_lead_id VARCHAR(64) NOT NULL,
  field_data_json JSON DEFAULT NULL,
  created_time DATETIME DEFAULT NULL,
  synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_form_lead (lead_form_id, fb_lead_id),
  CONSTRAINT fk_lead_form FOREIGN KEY (lead_form_id) REFERENCES lead_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- OAuth2 CSRF state tracking
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oauth_states (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state VARCHAR(64) NOT NULL UNIQUE,
  admin_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
