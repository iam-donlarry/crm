-- Migration 002: Create normalized role_permissions junction table and sync existing JSON roles permissions
-- Created: 2026-08-07

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_permission_id` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Populate role_permissions from existing JSON permission arrays stored in roles.permissions
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `SyncRolePermissionsFromJSON`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE r_id INT;
    DECLARE r_perms JSON;
    DECLARE p_idx INT;
    DECLARE p_count INT;
    DECLARE perm_id INT;
    
    DECLARE cur CURSOR FOR SELECT id, permissions FROM roles WHERE permissions IS NOT NULL AND permissions != '';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO r_id, r_perms;
        IF done THEN
            LEAVE read_loop;
        END IF;

        IF JSON_VALID(r_perms) THEN
            SET p_count = JSON_LENGTH(r_perms);
            SET p_idx = 0;

            WHILE p_idx < p_count DO
                SET perm_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(r_perms, CONCAT('$[', p_idx, ']'))) AS UNSIGNED);
                IF perm_id > 0 AND EXISTS (SELECT 1 FROM permissions WHERE id = perm_id) THEN
                    INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (r_id, perm_id);
                END IF;
                SET p_idx = p_idx + 1;
            END WHILE;
        END IF;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

CALL SyncRolePermissionsFromJSON();
DROP PROCEDURE IF EXISTS SyncRolePermissionsFromJSON;
