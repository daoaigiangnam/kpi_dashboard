CREATE TABLE pl2_approval_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    action_code VARCHAR(50) NOT NULL,
    result VARCHAR(30) NOT NULL,
    actor_type VARCHAR(40) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_name_snapshot VARCHAR(160) NOT NULL,
    actor_email_snapshot VARCHAR(190) NULL,
    channel VARCHAR(40) NOT NULL,
    note TEXT NULL,
    occurred_at DATETIME(3) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_pl2_approval_history_visit_time (visit_id, occurred_at),
    KEY idx_pl2_approval_history_actor (actor_user_id, occurred_at),
    CONSTRAINT fk_pl2_approval_history_visit FOREIGN KEY (visit_id) REFERENCES pl2_visits(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl2_approval_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl2_action_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME(3) NOT NULL,
    used_at DATETIME(3) NULL,
    revoked_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_pl2_action_tokens_hash (token_hash),
    KEY idx_pl2_action_tokens_visit_purpose_expiry (visit_id, purpose, expires_at),
    CONSTRAINT fk_pl2_action_tokens_visit FOREIGN KEY (visit_id) REFERENCES pl2_visits(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pl2_action_tokens_purpose CHECK (purpose IN ('APPROVAL', 'CHECKIN', 'CHECKOUT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl2_otp_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    requested_at DATETIME(3) NOT NULL,
    sent_at DATETIME(3) NULL,
    expires_at DATETIME(3) NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verified_at DATETIME(3) NULL,
    invalidated_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_pl2_otp_visit_status (visit_id, status),
    KEY idx_pl2_otp_expiry (expires_at),
    CONSTRAINT fk_pl2_otp_challenges_visit FOREIGN KEY (visit_id) REFERENCES pl2_visits(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pl2_otp_status CHECK (status IN ('PENDING', 'SENT', 'VERIFIED', 'EXPIRED', 'LOCKED', 'INVALIDATED', 'FAILED')),
    CONSTRAINT chk_pl2_otp_failed_attempts CHECK (failed_attempts <= 5),
    CONSTRAINT chk_pl2_otp_expiry_after_sent CHECK (expires_at IS NULL OR sent_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl2_otp_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    otp_challenge_id BIGINT UNSIGNED NOT NULL,
    attempted_at DATETIME(3) NOT NULL,
    result VARCHAR(20) NOT NULL,
    failure_code VARCHAR(60) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_pl2_otp_attempts_challenge_time (otp_challenge_id, attempted_at),
    CONSTRAINT fk_pl2_otp_attempts_challenge FOREIGN KEY (otp_challenge_id) REFERENCES pl2_otp_challenges(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pl2_otp_attempt_result CHECK (result IN ('SUCCESS', 'FAILED', 'LOCKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pl2_visit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    event_code VARCHAR(80) NOT NULL,
    actor_type VARCHAR(40) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_name_snapshot VARCHAR(160) NOT NULL,
    action_channel VARCHAR(40) NOT NULL,
    event_data JSON NULL,
    occurred_at DATETIME(3) NOT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_pl2_visit_events_visit_time (visit_id, occurred_at),
    KEY idx_pl2_visit_events_actor (actor_user_id, occurred_at),
    KEY idx_pl2_visit_events_code_time (event_code, occurred_at),
    CONSTRAINT fk_pl2_visit_events_visit FOREIGN KEY (visit_id) REFERENCES pl2_visits(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl2_visit_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
