CREATE TABLE pl2_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_entity_id BIGINT UNSIGNED NOT NULL,
    visit_code VARCHAR(60) NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    requester_name VARCHAR(160) NOT NULL,
    requester_email VARCHAR(190) NOT NULL,
    organization VARCHAR(160) NULL,
    visitor_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    purpose TEXT NOT NULL,
    supervisor_name VARCHAR(160) NOT NULL,
    planned_entry_at DATETIME(3) NOT NULL,
    approver_name_snapshot VARCHAR(160) NOT NULL,
    approver_email_snapshot VARCHAR(190) NOT NULL,
    approval_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    visit_status VARCHAR(30) NOT NULL DEFAULT 'NOT_CHECKED_IN',
    approval_deadline_at DATETIME(3) NOT NULL,
    approved_at DATETIME(3) NULL,
    approval_note TEXT NULL,
    checked_in_at DATETIME(3) NULL,
    checked_in_person_name VARCHAR(160) NULL,
    checked_in_actor_name VARCHAR(160) NULL,
    checked_out_at DATETIME(3) NULL,
    checked_out_actor_name VARCHAR(160) NULL,
    work_result TEXT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_pl2_visits_entity (business_entity_id),
    UNIQUE KEY uq_pl2_visits_code (visit_code),
    KEY idx_pl2_visits_approval_created (approval_status, created_at),
    KEY idx_pl2_visits_room_planned_entry (room_id, planned_entry_at),
    KEY idx_pl2_visits_status_checkin (visit_status, checked_in_at),
    KEY idx_pl2_visits_requester (requester_email, created_at),
    CONSTRAINT fk_pl2_visits_entity FOREIGN KEY (business_entity_id) REFERENCES business_entities(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pl2_visits_room FOREIGN KEY (room_id) REFERENCES server_rooms(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_pl2_visits_approval_status CHECK (approval_status IN ('PENDING', 'APPROVED', 'REJECTED', 'EXPIRED')),
    CONSTRAINT chk_pl2_visits_visit_status CHECK (visit_status IN ('NOT_CHECKED_IN', 'OPEN', 'CLOSED')),
    CONSTRAINT chk_pl2_visits_visitor_count CHECK (visitor_count >= 1),
    CONSTRAINT chk_pl2_visits_checkin_fields CHECK (
        (visit_status = 'NOT_CHECKED_IN' AND checked_in_at IS NULL) OR
        (visit_status IN ('OPEN', 'CLOSED') AND checked_in_at IS NOT NULL AND checked_in_person_name IS NOT NULL)
    ),
    CONSTRAINT chk_pl2_visits_checkout_fields CHECK (
        (visit_status IN ('NOT_CHECKED_IN', 'OPEN') AND checked_out_at IS NULL) OR
        (visit_status = 'CLOSED' AND checked_out_at IS NOT NULL AND work_result IS NOT NULL)
    ),
    CONSTRAINT chk_pl2_visits_approval_time CHECK (
        (approval_status = 'APPROVED' AND approved_at IS NOT NULL) OR
        (approval_status <> 'APPROVED')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
