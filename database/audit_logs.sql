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