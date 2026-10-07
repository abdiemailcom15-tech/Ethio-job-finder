USE job_finder_system;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INT UNSIGNED PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    agreed_amount DECIMAL(12, 2) NOT NULL,
    scope TEXT NULL,
    status ENUM('in_progress', 'submitted', 'revision_requested', 'invoiced', 'paid', 'completed') NOT NULL DEFAULT 'in_progress',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_order_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT UNSIGNED NOT NULL,
    message TEXT NULL,
    review_note TEXT NULL,
    status ENUM('submitted', 'revision_requested', 'approved') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_submissions_work_order (work_order_id, submitted_at),
    CONSTRAINT fk_submission_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_submission_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_file FOREIGN KEY (submission_id) REFERENCES work_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT UNSIGNED NOT NULL UNIQUE,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    payment_reference VARCHAR(120) NULL,
    payment_proof_filename VARCHAR(255) NULL,
    payment_proof_mime VARCHAR(120) NULL,
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    CONSTRAINT fk_invoice_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS work_order_chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NULL,
    attachment_filename VARCHAR(255) NULL,
    attachment_original_name VARCHAR(255) NULL,
    attachment_mime VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_work_chat_messages (work_order_id, id),
    CONSTRAINT fk_work_chat_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_work_chat_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'companies' AND column_name = 'phone') = 0,
    'ALTER TABLE companies ADD COLUMN phone VARCHAR(40) NULL AFTER name',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE seeker_profiles SET phone = NULL WHERE phone = '';
UPDATE seeker_profiles SET payout_account_number = NULL WHERE payout_account_number = '';
UPDATE companies SET phone = NULL WHERE phone = '';

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'companies' AND index_name = 'uq_companies_phone') = 0
        AND (SELECT COUNT(*) FROM (SELECT phone FROM companies WHERE phone IS NOT NULL AND phone <> '' GROUP BY phone HAVING COUNT(*) > 1) duplicates) = 0,
    'ALTER TABLE companies ADD UNIQUE KEY uq_companies_phone (phone)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND index_name = 'uq_seeker_phone') = 0
        AND (SELECT COUNT(*) FROM (SELECT phone FROM seeker_profiles WHERE phone IS NOT NULL AND phone <> '' GROUP BY phone HAVING COUNT(*) > 1) duplicates) = 0,
    'ALTER TABLE seeker_profiles ADD UNIQUE KEY uq_seeker_phone (phone)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND index_name = 'uq_seeker_payout_account') = 0
        AND (SELECT COUNT(*) FROM (SELECT payout_account_number FROM seeker_profiles WHERE payout_account_number IS NOT NULL AND payout_account_number <> '' GROUP BY payout_account_number HAVING COUNT(*) > 1) duplicates) = 0,
    'ALTER TABLE seeker_profiles ADD UNIQUE KEY uq_seeker_payout_account (payout_account_number)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'payment_proof_filename') = 0,
    'ALTER TABLE invoices ADD COLUMN payment_proof_filename VARCHAR(255) NULL AFTER payment_reference',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'payment_proof_mime') = 0,
    'ALTER TABLE invoices ADD COLUMN payment_proof_mime VARCHAR(120) NULL AFTER payment_proof_filename',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

CREATE TABLE IF NOT EXISTS work_order_issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT UNSIGNED NOT NULL,
    raised_by INT UNSIGNED NOT NULL,
    issue_type ENUM('payment', 'work_quality', 'agreement', 'other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_review', 'resolved') NOT NULL DEFAULT 'open',
    admin_response TEXT NULL,
    resolved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_work_order_issues_status (status, created_at),
    CONSTRAINT fk_work_issue_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_work_issue_raiser FOREIGN KEY (raised_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_work_issue_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'duration_days') = 0,
    'ALTER TABLE jobs ADD COLUMN duration_days SMALLINT UNSIGNED NULL AFTER requirements',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'deadline') = 0,
    'ALTER TABLE jobs ADD COLUMN deadline DATE NULL AFTER duration_days',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'latitude') = 0,
    'ALTER TABLE jobs ADD COLUMN latitude DECIMAL(10, 7) NULL AFTER location',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'jobs' AND column_name = 'longitude') = 0,
    'ALTER TABLE jobs ADD COLUMN longitude DECIMAL(10, 7) NULL AFTER latitude',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND column_name = 'payout_provider') = 0,
    'ALTER TABLE seeker_profiles ADD COLUMN payout_provider VARCHAR(50) NULL AFTER cv_filename',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND column_name = 'payout_provider_other') = 0,
    'ALTER TABLE seeker_profiles ADD COLUMN payout_provider_other VARCHAR(100) NULL AFTER payout_provider',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND column_name = 'payout_account_name') = 0,
    'ALTER TABLE seeker_profiles ADD COLUMN payout_account_name VARCHAR(120) NULL AFTER payout_provider_other',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'seeker_profiles' AND column_name = 'payout_account_number') = 0,
    'ALTER TABLE seeker_profiles ADD COLUMN payout_account_number VARCHAR(100) NULL AFTER payout_account_name',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
