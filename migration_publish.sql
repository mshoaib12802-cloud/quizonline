USE quizonline;

-- Helper stored procedure to add column only if it doesn't already exist
DROP PROCEDURE IF EXISTS AddPublishColumnIfNotExists;
DELIMITER //
CREATE PROCEDURE AddPublishColumnIfNotExists(
    IN tableName VARCHAR(64),
    IN colName VARCHAR(64),
    IN colDef TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = tableName
        AND COLUMN_NAME = colName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD COLUMN ', colName, ' ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL AddPublishColumnIfNotExists('quizzes', 'publish_status', "ENUM('draft','published') NOT NULL DEFAULT 'draft' AFTER is_active");
CALL AddPublishColumnIfNotExists('quizzes', 'published_at', "DATETIME NULL AFTER publish_status");

DROP PROCEDURE IF EXISTS AddPublishColumnIfNotExists;

UPDATE quizzes
SET publish_status='published', published_at=NOW()
WHERE is_active=1 AND (publish_status IS NULL OR publish_status='draft');
