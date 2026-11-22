-- UOA Server Room Management
-- MySQL 8.0+, InnoDB, utf8mb4. All timestamps are stored in UTC.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS server_rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(40) NOT NULL,
  room_name VARCHAR(160) NOT NULL,
  location VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_server_rooms_code (room_code),
  KEY idx_server_rooms_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS room_approvers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  approver_name VARCHAR(160) NOT NULL,
  approver_email VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  approval_timeout_hours INT UNSIGNED NOT NULL DEFAULT 24,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_room_approvers_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_room_approvers_user FOREIGN KEY (user_id) REFERENCES users(id),
  KEY idx_room_approvers_active (room_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS access_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_code VARCHAR(50) NOT NULL,
  request_type VARCHAR(20) NOT NULL,
  person_name VARCHAR(160) NOT NULL,
  person_email VARCHAR(190) NOT NULL,
  organization VARCHAR(160) NOT NULL,
  role_reason VARCHAR(255) NOT NULL,
  access_level VARCHAR(30) NOT NULL,
  valid_from DATE NULL,
  valid_until DATE NULL,
  requester_name VARCHAR(160) NOT NULL,
  requester_email VARCHAR(190) NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  approver_name_snapshot VARCHAR(160) NOT NULL,
  approver_email_snapshot VARCHAR(190) NOT NULL,
  approval_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  approval_token_hash CHAR(64) NULL,
  token_expires_at DATETIME NULL,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decision_note TEXT NULL,
  source_response_id VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_access_requests_code (request_code),
  UNIQUE KEY uq_access_requests_source (source_response_id),
  KEY idx_access_requests_status (approval_status, created_at),
  KEY idx_access_requests_person_room (person_email, room_id, approval_status),
  CONSTRAINT fk_access_requests_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_access_requests_decider FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS authorized_personnel (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  access_request_id BIGINT UNSIGNED NOT NULL,
  person_name VARCHAR(160) NOT NULL,
  person_email VARCHAR(190) NOT NULL,
  organization VARCHAR(160) NOT NULL,
  role_reason VARCHAR(255) NOT NULL,
  access_level VARCHAR(30) NOT NULL,
  valid_from DATE NULL,
  valid_until DATE NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  record_status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_authorized_request (access_request_id),
  KEY idx_authorized_person_room (person_email, room_id, record_status),
  CONSTRAINT fk_authorized_request FOREIGN KEY (access_request_id) REFERENCES access_requests(id),
  CONSTRAINT fk_authorized_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_authorized_approver FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_code VARCHAR(60) NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  requester_name VARCHAR(160) NOT NULL,
  requester_email VARCHAR(190) NOT NULL,
  organization VARCHAR(160) NOT NULL,
  visitor_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  planned_entry_at DATETIME NOT NULL,
  purpose TEXT NOT NULL,
  supervisor_name VARCHAR(160) NOT NULL,
  approver_name_snapshot VARCHAR(160) NOT NULL,
  approver_email_snapshot VARCHAR(190) NOT NULL,
  approval_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  visit_status VARCHAR(30) NOT NULL DEFAULT 'NOT_CHECKED_IN',
  approval_token_hash CHAR(64) NULL,
  checkin_token_hash CHAR(64) NULL,
  checkout_token_hash CHAR(64) NULL,
  otp_hash CHAR(64) NULL,
  otp_expires_at DATETIME NULL,
  otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  checked_in_at DATETIME NULL,
  checked_in_by VARCHAR(160) NULL,
  checked_out_at DATETIME NULL,
  checked_out_by VARCHAR(160) NULL,
  work_result TEXT NULL,
  work_image_path VARCHAR(500) NULL,
  approval_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_visits_code (visit_code),
  KEY idx_visits_status (approval_status, visit_status, planned_entry_at),
  KEY idx_visits_room_date (room_id, planned_entry_at),
  CONSTRAINT fk_visits_room FOREIGN KEY (room_id) REFERENCES server_rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS inspection_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(20) NOT NULL,
  description_en TEXT NOT NULL,
  description_vi TEXT NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_inspection_items_code (item_code),
  KEY idx_inspection_items_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS inspections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inspection_code VARCHAR(60) NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  frequency VARCHAR(20) NOT NULL,
  inspection_date DATE NOT NULL,
  inspection_time TIME NOT NULL,
  inspector_name VARCHAR(160) NOT NULL,
  inspector_email VARCHAR(190) NULL,
  temperature_c DECIMAL(5,2) NULL,
  humidity_percent DECIMAL(5,2) NULL,
  conclusion VARCHAR(40) NOT NULL,
  signature_reference VARCHAR(500) NULL,
  uoa_confirmation_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  uoa_confirmed_by BIGINT UNSIGNED NULL,
  uoa_confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inspections_code (inspection_code),
  KEY idx_inspections_room_date (room_id, inspection_date),
  KEY idx_inspections_confirmation (uoa_confirmation_status),
  CONSTRAINT fk_inspections_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_inspections_confirmer FOREIGN KEY (uoa_confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS inspection_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inspection_id BIGINT UNSIGNED NOT NULL,
  inspection_item_id BIGINT UNSIGNED NOT NULL,
  result VARCHAR(10) NOT NULL,
  remarks_action TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inspection_result (inspection_id, inspection_item_id),
  KEY idx_inspection_results_result (result),
  CONSTRAINT fk_inspection_results_inspection FOREIGN KEY (inspection_id) REFERENCES inspections(id),
  CONSTRAINT fk_inspection_results_item FOREIGN KEY (inspection_item_id) REFERENCES inspection_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_code VARCHAR(60) NOT NULL,
  source_type VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
  inspection_id BIGINT UNSIGNED NULL,
  inspection_result_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  detected_at DATETIME NOT NULL,
  reporter_name VARCHAR(160) NOT NULL,
  reporter_organization VARCHAR(160) NULL,
  location_equipment VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  impact_level VARCHAR(30) NOT NULL,
  immediate_action TEXT NULL,
  notified_party_time TEXT NULL,
  root_cause TEXT NULL,
  corrective_action TEXT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  due_at DATETIME NULL,
  closure_result TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
  closed_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_incidents_code (incident_code),
  UNIQUE KEY uq_incidents_inspection_result (inspection_result_id),
  KEY idx_incidents_status_due (status, due_at),
  CONSTRAINT fk_incidents_inspection FOREIGN KEY (inspection_id) REFERENCES inspections(id),
  CONSTRAINT fk_incidents_inspection_result FOREIGN KEY (inspection_result_id) REFERENCES inspection_results(id),
  CONSTRAINT fk_incidents_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_incidents_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_incidents_closer FOREIGN KEY (closed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS work_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_code VARCHAR(60) NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  work_window_start DATETIME NOT NULL,
  work_window_end DATETIME NOT NULL,
  implementing_organization VARCHAR(190) NOT NULL,
  implementing_personnel TEXT NOT NULL,
  related_equipment TEXT NOT NULL,
  work_description TEXT NOT NULL,
  expected_service_impact TEXT NULL,
  rollback_plan TEXT NOT NULL,
  uoa_approver_id BIGINT UNSIGNED NULL,
  approval_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  work_status VARCHAR(30) NOT NULL DEFAULT 'NOT_STARTED',
  post_work_result TEXT NULL,
  verified_by BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_work_requests_code (work_code),
  KEY idx_work_requests_status_window (approval_status, work_status, work_window_start),
  CONSTRAINT fk_work_requests_room FOREIGN KEY (room_id) REFERENCES server_rooms(id),
  CONSTRAINT fk_work_requests_approver FOREIGN KEY (uoa_approver_id) REFERENCES users(id),
  CONSTRAINT fk_work_requests_verifier FOREIGN KEY (verified_by) REFERENCES users(id),
  CONSTRAINT fk_work_requests_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS email_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue_code CHAR(36) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  module_code VARCHAR(20) NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  recipient VARCHAR(500) NOT NULL,
  cc_json JSON NULL,
  bcc_json JSON NULL,
  sender_name VARCHAR(160) NULL,
  subject VARCHAR(255) NOT NULL,
  plain_body MEDIUMTEXT NULL,
  html_body MEDIUMTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at DATETIME NULL,
  sent_at DATETIME NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(100) NULL,
  last_error TEXT NULL,
  message_id VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_queue_code (queue_code),
  UNIQUE KEY uq_email_queue_event_key (event_key),
  KEY idx_email_queue_worker (status, next_attempt_at, id),
  KEY idx_email_queue_entity (module_code, entity_type, entity_id),
  CONSTRAINT chk_email_queue_status CHECK (status IN ('PENDING','PROCESSING','RETRY','SENT','FAILED','CANCELLED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS email_queue_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_queue_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  attempted_at DATETIME NOT NULL,
  outcome VARCHAR(20) NOT NULL,
  error_message TEXT NULL,
  smtp_message_id VARCHAR(255) NULL,
  duration_ms INT UNSIGNED NULL,
  UNIQUE KEY uq_email_attempt_no (email_queue_id, attempt_no),
  CONSTRAINT fk_email_attempt_queue FOREIGN KEY (email_queue_id) REFERENCES email_queue(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_label VARCHAR(190) NOT NULL,
  module_code VARCHAR(20) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (module_code, entity_type, entity_id, created_at),
  KEY idx_audit_actor (actor_user_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  description VARCHAR(255) NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_system_settings_key (setting_key),
  CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
