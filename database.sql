CREATE DATABASE IF NOT EXISTS job_finder_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE job_finder_system;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'seeker', 'employer') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INT UNSIGNED PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seeker_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    phone VARCHAR(40) NULL,
    UNIQUE KEY uq_seeker_phone (phone),
    location VARCHAR(120) NULL,
    skills TEXT NULL,
    bio TEXT NULL,
    cv_filename VARCHAR(255) NULL,
    payout_provider VARCHAR(50) NULL,
    payout_provider_other VARCHAR(100) NULL,
    payout_account_name VARCHAR(120) NULL,
    payout_account_number VARCHAR(100) NULL,
    UNIQUE KEY uq_seeker_payout_account (payout_account_number),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_seeker_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    UNIQUE KEY uq_companies_phone (phone),
    website VARCHAR(255) NULL,
    location VARCHAR(120) NULL,
    description TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    category VARCHAR(100) NOT NULL,
    location VARCHAR(120) NOT NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    job_type ENUM('Full-time', 'Part-time', 'Contract', 'Internship', 'Remote') NOT NULL DEFAULT 'Full-time',
    salary VARCHAR(100) NULL,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    duration_days SMALLINT UNSIGNED NULL,
    deadline DATE NULL,
    status ENUM('pending', 'approved', 'rejected', 'closed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jobs_search (status, category, location),
    CONSTRAINT fk_job_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    seeker_id INT UNSIGNED NOT NULL,
    cover_letter TEXT NULL,
    status ENUM('submitted', 'reviewing', 'accepted', 'rejected') NOT NULL DEFAULT 'submitted',
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_seeker (job_id, seeker_id),
    INDEX idx_applications_seeker (seeker_id, applied_at),
    CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_seeker FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Default local-development admin account.
-- Change this password immediately after the first sign-in.
INSERT IGNORE INTO users (name, email, password, role)
VALUES (
    'Administrator',
    'admin@gmail.com',
    '$2y$10$dK7uVfH4Ph0HeaRK6D3FneM7cqdnSrRZ2Ku7qxMriS9/WYD/nGlsi',
    'admin'
);
