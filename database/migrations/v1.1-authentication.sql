-- database/migrations/v1.1-authentication.sql
-- Authentication system enhancements

USE campusconnect_db;

-- Add indexes for faster authentication queries
CREATE INDEX IF NOT EXISTS idx_users_login ON users(campus_email, is_active, is_verified);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token, expires_at);
CREATE INDEX IF NOT EXISTS idx_email_verifications_token ON email_verifications(token, expires_at);

-- Add trigger to automatically create email verification on registration
DELIMITER //
CREATE TRIGGER after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    -- Only create verification for non-admin, non-verified users
    IF NEW.role = 'student' AND NOT NEW.is_verified THEN
        INSERT INTO email_verifications (user_id, token, expires_at)
        VALUES (
            NEW.id,
            SHA2(CONCAT(NEW.id, NEW.campus_email, NOW(), RAND()), 256),
            DATE_ADD(NOW(), INTERVAL 24 HOUR)
        );
    END IF;
END //
DELIMITER ;

-- Create stored procedure for user cleanup
DELIMITER //
CREATE PROCEDURE cleanup_expired_tokens()
BEGIN
    -- Delete expired password reset tokens
    DELETE FROM password_resets WHERE expires_at < NOW();
    
    -- Delete expired email verification tokens
    DELETE FROM email_verifications WHERE expires_at < NOW();
    
    -- Delete expired JWT tokens from blacklist
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- Create event to run cleanup daily (requires EVENT_SCHEDULER enabled)
-- SET GLOBAL event_scheduler = ON;
-- 
-- CREATE EVENT IF NOT EXISTS daily_token_cleanup
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
-- BEGIN
--     CALL cleanup_expired_tokens();
-- END;

-- Add column for account verification method
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS verification_method ENUM('email', 'manual', 'admin') DEFAULT 'email',
ADD COLUMN IF NOT EXISTS verification_date TIMESTAMP NULL;

-- Update existing verified users
UPDATE users 
SET verification_date = created_at 
WHERE is_verified = TRUE AND verification_date IS NULL;

-- Create view for admin to see pending verifications
CREATE OR REPLACE VIEW pending_verifications AS
SELECT 
    u.id,
    u.registration_number,
    u.campus_email,
    u.full_name,
    u.course,
    u.year_of_study,
    u.created_at as registered_at,
    ev.expires_at as token_expires
FROM users u
LEFT JOIN email_verifications ev ON u.id = ev.user_id
WHERE u.is_verified = FALSE 
AND u.role = 'student'
AND u.is_active = TRUE
ORDER BY u.created_at DESC;

-- Add login attempt logging (enhanced)
ALTER TABLE login_attempts 
ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255),
ADD COLUMN IF NOT EXISTS location VARCHAR(100);

-- Create function to check if user can attempt login
DELIMITER //
CREATE FUNCTION can_attempt_login(email VARCHAR(100)) 
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE recent_failures INT;
    
    SELECT COUNT(*) INTO recent_failures
    FROM login_attempts
    WHERE email = email 
    AND success = FALSE
    AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE);
    
    RETURN recent_failures < 5;
END //
DELIMITER ;

-- Show migration results
SELECT 'Authentication Migration Complete' as message;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as pending_verifications FROM pending_verifications;