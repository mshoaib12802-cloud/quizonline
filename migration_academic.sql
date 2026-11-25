USE quizonline;

-- Helper stored procedure to add column only if it doesn't already exist
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DELIMITER //
CREATE PROCEDURE AddColumnIfNotExists(
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

CALL AddColumnIfNotExists('users', 'roll_number', 'VARCHAR(50) NULL UNIQUE AFTER email');
CALL AddColumnIfNotExists('users', 'semester', 'TINYINT NULL AFTER roll_number');
CALL AddColumnIfNotExists('users', 'department', 'VARCHAR(120) NULL AFTER semester');
CALL AddColumnIfNotExists('users', 'phone', 'VARCHAR(30) NULL AFTER department');
CALL AddColumnIfNotExists('users', 'gender', 'VARCHAR(20) NULL AFTER phone');
CALL AddColumnIfNotExists('users', 'profile_image', 'VARCHAR(255) NULL AFTER gender');
CALL AddColumnIfNotExists('users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER role');

CALL AddColumnIfNotExists('quizzes', 'semester', 'TINYINT NOT NULL DEFAULT 1 AFTER category_id');
CALL AddColumnIfNotExists('quizzes', 'total_marks', 'INT NOT NULL DEFAULT 0 AFTER time_limit');

CALL AddColumnIfNotExists('questions', 'question_type', "ENUM('mcq','short') NOT NULL DEFAULT 'mcq' AFTER question_text");
CALL AddColumnIfNotExists('questions', 'marks', 'INT NOT NULL DEFAULT 1 AFTER correct_option');

CALL AddColumnIfNotExists('attempts', 'status', "ENUM('submitted','graded','published') NOT NULL DEFAULT 'submitted' AFTER percentage");
CALL AddColumnIfNotExists('attempts', 'teacher_feedback', 'TEXT NULL AFTER status');
CALL AddColumnIfNotExists('attempts', 'graded_at', 'DATETIME NULL AFTER teacher_feedback');

CALL AddColumnIfNotExists('attempt_answers', 'text_answer', 'TEXT NULL AFTER selected_option');
CALL AddColumnIfNotExists('attempt_answers', 'awarded_marks', 'DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER is_correct');
CALL AddColumnIfNotExists('attempt_answers', 'feedback', 'TEXT NULL AFTER awarded_marks');

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

UPDATE questions SET marks=1 WHERE marks IS NULL OR marks=0;
UPDATE quizzes q SET total_marks=(SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=q.id);
UPDATE attempts SET status='published' WHERE status IS NULL;
