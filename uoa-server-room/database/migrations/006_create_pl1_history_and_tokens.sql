CREATE TABLE pl1_approval_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    access_request_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(20) NOT NULL,
    approver_user_id BIGINT UNSIGNED NULL,
    approver_name_snapshot VARCHAR(160) NOT NULL,
    approver_email_snapshot VARCHAR(190) NOT NULL,
    channel VARCHAR(30) NOT NULL,
    note TEXT NULL,
    decided_at DATETIME(3) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_pl1_approval_decisions_request_time (access_request_id, decided_at),
    KEY idx_pl1_approval_decisions_approver (approver_user_id, decided_at),
    CONSTRAINT fk_pl1_approval_decisions_request FOREIGN KEY (access_request_id) REFERENCES pl1_access_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl1_approval_decisions_approver FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pl1_approval_decision CHECK (decision IN ('APPROVED', 'REJECTED')),
    CONSTRAINT chk_pl1_approval_channel CHECK (channel IN ('EMAIL_APPROVAL', 'ADMIN'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl1_access_record_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    access_record_id BIGINT UNSIGNED NOT NULL,
    access_request_id BIGINT UNSIGNED NOT NULL,
    change_type VARCHAR(30) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    actor_type VARCHAR(40) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_name_snapshot VARCHAR(160) NOT NULL,
    action_channel VARCHAR(40) NOT NULL,
    changed_at DATETIME(3) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_pl1_access_history_record_time (access_record_id, changed_at),
    KEY idx_pl1_access_history_request (access_request_id),
    KEY idx_pl1_access_history_actor (actor_user_id, changed_at),
    CONSTRAINT fk_pl1_access_history_record FOREIGN KEY (access_record_id) REFERENCES pl1_access_records(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl1_access_history_request FOREIGN KEY (access_request_id) REFERENCES pl1_access_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl1_access_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl1_action_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    access_request_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(30) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME(3) NOT NULL,
    used_at DATETIME(3) NULL,
    revoked_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_pl1_action_tokens_hash (token_hash),
    KEY idx_pl1_action_tokens_request (access_request_id, purpose, expires_at),
    CONSTRAINT fk_pl1_action_tokens_request FOREIGN KEY (access_request_id) REFERENCES pl1_access_requests(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
