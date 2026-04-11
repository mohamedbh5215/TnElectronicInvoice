-- Table de traçabilité pour le module Facture TTN
-- Structure de la table llx_facture_ttn_log

CREATE TABLE IF NOT EXISTS llx_facture_ttn_log (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_facture      INTEGER NOT NULL,                -- Référence à llx_facture
    status_ttn      VARCHAR(50) DEFAULT 'PENDING',   -- Statut de soumission TTN (PENDING, SENT, ACCEPTED, REJECTED)
    ref_ttn         VARCHAR(128) DEFAULT NULL,       -- Référence retournée par le système TTN
    file_hash       VARCHAR(64) DEFAULT NULL,        -- Hash SHA-256 du fichier exporté
    exported_file   VARCHAR(255) DEFAULT NULL,       -- Chemin ou nom du fichier exporté
    date_export     DATETIME DEFAULT NULL,           -- Date et heure de l'export/submit
    fk_user         INTEGER DEFAULT NULL,            -- Utilisateur ayant effectué l'action
    notes           TEXT DEFAULT NULL,               -- Notes additionnelles (erreurs, commentaires)
    date_creation   DATETIME NOT NULL,               -- Date de création de l'enregistrement
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    import_key      VARCHAR(14) DEFAULT NULL,        -- Clé d'import externe
    model_pdf       VARCHAR(50) DEFAULT NULL,        -- Modèle PDF utilisé (optionnel)
    
    -- Contraintes d'intégrité référentielle
    CONSTRAINT fk_facture_ttn_log_facture FOREIGN KEY (fk_facture) 
        REFERENCES llx_facture(rowid) ON DELETE CASCADE,
    CONSTRAINT fk_facture_ttn_log_user FOREIGN KEY (fk_user) 
        REFERENCES llx_user(rowid) ON DELETE SET NULL,
    
    -- Index pour optimiser les recherches
    INDEX idx_facture_ttn_log_fk_facture (fk_facture),
    INDEX idx_facture_ttn_log_status_ttn (status_ttn),
    INDEX idx_facture_ttn_log_ref_ttn (ref_ttn),
    INDEX idx_facture_ttn_log_date_export (date_export)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commentaires sur la table (si supporté par MySQL/MariaDB)
ALTER TABLE llx_facture_ttn_log COMMENT 'Table de traçabilité des factures électroniques TTN';

-- Insertion d'une donnée de test (optionnelle, à commenter en production)
-- INSERT INTO llx_facture_ttn_log (fk_facture, status_ttn, date_creation, fk_user) 
-- VALUES (1, 'PENDING', NOW(), 1);
