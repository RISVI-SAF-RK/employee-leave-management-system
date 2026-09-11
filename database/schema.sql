-- ============================================================
-- Employee Leave Management System (ELMS)
-- Database Schema
-- Database: MySQL
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- ============================================================
-- 1. ROLES TABLE
-- Stores the system user roles:
-- Administrator, Manager, Employee
-- ============================================================

CREATE TABLE roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. DEPARTMENTS TABLE
-- Stores organizational departments
-- ============================================================

CREATE TABLE departments (
    department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. USERS TABLE
-- Stores login and authentication information
-- ============================================================

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    email VARCHAR(150) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    role_id INT UNSIGNED NOT NULL,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    last_login DATETIME DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. EMPLOYEES TABLE
-- Stores employee profile information
--
-- manager_id references another employee record.
-- This allows each employee to be assigned to a Manager.
-- ============================================================

CREATE TABLE employees (
    employee_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    employee_code VARCHAR(30) NOT NULL UNIQUE,

    first_name VARCHAR(100) NOT NULL,

    last_name VARCHAR(100) NOT NULL,

    phone VARCHAR(20) DEFAULT NULL,

    department_id INT UNSIGNED NOT NULL,

    manager_id INT UNSIGNED DEFAULT NULL,

    job_title VARCHAR(100) NOT NULL,

    date_joined DATE NOT NULL,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_employees_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_employees_manager
        FOREIGN KEY (manager_id)
        REFERENCES employees(employee_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_employee_department (department_id),

    INDEX idx_employee_manager (manager_id),

    INDEX idx_employee_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. LEAVE TYPES TABLE
-- Stores different types of employee leave
-- ============================================================

CREATE TABLE leave_types (
    leave_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    leave_type_name VARCHAR(100) NOT NULL UNIQUE,

    description VARCHAR(255) DEFAULT NULL,

    is_paid BOOLEAN NOT NULL DEFAULT TRUE,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. LEAVE POLICIES TABLE
-- Stores organizational rules for each leave type
-- ============================================================

CREATE TABLE leave_policies (
    policy_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    leave_type_id INT UNSIGNED NOT NULL UNIQUE,

    days_per_year DECIMAL(5,2)
        NOT NULL DEFAULT 0.00,

    minimum_service_months INT UNSIGNED
        NOT NULL DEFAULT 0,

    carry_forward_allowed BOOLEAN
        NOT NULL DEFAULT FALSE,

    max_carry_forward_days DECIMAL(5,2)
        NOT NULL DEFAULT 0.00,

    requires_attachment BOOLEAN
        NOT NULL DEFAULT FALSE,

    status ENUM('Active', 'Inactive')
        NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_policies_type
        FOREIGN KEY (leave_type_id)
        REFERENCES leave_types(leave_type_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. LEAVE BALANCES TABLE
-- Stores yearly leave balances for each employee
-- and each leave type
-- ============================================================

CREATE TABLE leave_balances (
    balance_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_id INT UNSIGNED NOT NULL,

    leave_type_id INT UNSIGNED NOT NULL,

    balance_year YEAR NOT NULL,

    allocated_days DECIMAL(5,2)
        NOT NULL DEFAULT 0.00,

    used_days DECIMAL(5,2)
        NOT NULL DEFAULT 0.00,

    remaining_days DECIMAL(5,2)
        NOT NULL DEFAULT 0.00,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_balances_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(employee_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_leave_balances_type
        FOREIGN KEY (leave_type_id)
        REFERENCES leave_types(leave_type_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT uq_employee_leave_balance
        UNIQUE (
            employee_id,
            leave_type_id,
            balance_year
        ),

    INDEX idx_balance_employee (employee_id),

    INDEX idx_balance_leave_type (leave_type_id),

    INDEX idx_balance_year (balance_year)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. LEAVE APPLICATIONS TABLE
-- Stores employee leave requests
-- ============================================================

CREATE TABLE leave_applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_id INT UNSIGNED NOT NULL,

    leave_type_id INT UNSIGNED NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    number_of_days DECIMAL(5,2) NOT NULL,

    reason TEXT NOT NULL,

    attachment VARCHAR(255) DEFAULT NULL,

    status ENUM(
        'Pending',
        'Approved',
        'Rejected'
    ) NOT NULL DEFAULT 'Pending',

    applied_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_applications_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(employee_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_leave_applications_type
        FOREIGN KEY (leave_type_id)
        REFERENCES leave_types(leave_type_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_application_employee (employee_id),

    INDEX idx_application_leave_type (leave_type_id),

    INDEX idx_application_status (status),

    INDEX idx_application_dates (
        start_date,
        end_date
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 9. LEAVE APPROVALS TABLE
-- Stores the Manager's final decision
-- One application can have at most one final approval record.
-- ============================================================

CREATE TABLE leave_approvals (
    approval_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    application_id INT UNSIGNED NOT NULL UNIQUE,

    manager_id INT UNSIGNED NOT NULL,

    decision ENUM(
        'Approved',
        'Rejected'
    ) NOT NULL,

    comment TEXT DEFAULT NULL,

    decision_date TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_approvals_application
        FOREIGN KEY (application_id)
        REFERENCES leave_applications(application_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_leave_approvals_manager
        FOREIGN KEY (manager_id)
        REFERENCES employees(employee_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_approval_manager (manager_id),

    INDEX idx_approval_decision (decision)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 10. NOTIFICATIONS TABLE
-- Stores notifications for Employees, Managers,
-- and Administrators
-- ============================================================

CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    is_read BOOLEAN
        NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    INDEX idx_notification_user (user_id),

    INDEX idx_notification_read (is_read),

    INDEX idx_notification_created (created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE audit_logs (

    audit_id INT UNSIGNED
        AUTO_INCREMENT
        PRIMARY KEY,

    user_id INT UNSIGNED
        DEFAULT NULL,

    action VARCHAR(100)
        NOT NULL,

    entity_type VARCHAR(60)
        DEFAULT NULL,

    entity_id INT UNSIGNED
        DEFAULT NULL,

    description VARCHAR(500)
        DEFAULT NULL,

    ip_address VARCHAR(45)
        DEFAULT NULL,

    user_agent VARCHAR(255)
        DEFAULT NULL,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_audit_user (user_id),

    INDEX idx_audit_action (action),

    INDEX idx_audit_entity (
        entity_type,
        entity_id
    ),

    INDEX idx_audit_created (
        created_at
    )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;