CREATE TABLE business_entities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_code VARCHAR(10) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    business_code VARCHAR(60) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_entities_code (module_code, entity_type, business_code),
    KEY idx_business_entities_module_type_id (module_code, entity_type, id),
    CONSTRAINT chk_business_entities_module CHECK (module_code IN ('PL1', 'PL2', 'PL3', 'PL4', 'PL5'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
