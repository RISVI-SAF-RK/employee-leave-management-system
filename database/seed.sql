-- ============================================================
-- Employee Leave Management System (ELMS)
-- Initial Seed Data
-- ============================================================


-- ============================================================
-- 1. SYSTEM ROLES
-- ============================================================

INSERT IGNORE INTO roles (role_name)
VALUES
    ('Administrator'),
    ('Manager'),
    ('Employee');


-- ============================================================
-- 2. LEAVE TYPES
-- ============================================================

INSERT IGNORE INTO leave_types
(
    leave_type_name,
    description,
    is_paid,
    status
)
VALUES
(
    'Annual Leave',
    'Annual leave provided according to organizational policy.',
    TRUE,
    'Active'
),
(
    'Casual Leave',
    'Casual leave provided according to organizational policy.',
    TRUE,
    'Active'
),
(
    'Sick Leave',
    'Leave provided when an employee is unable to work due to illness.',
    TRUE,
    'Active'
),
(
    'Maternity Leave',
    'Maternity leave provided according to organizational policy.',
    TRUE,
    'Active'
),
(
    'Paternity Leave',
    'Paternity leave provided according to organizational policy.',
    TRUE,
    'Active'
),
(
    'Unpaid Leave',
    'Leave granted without salary according to organizational policy.',
    FALSE,
    'Active'
);