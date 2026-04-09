-- ============================================================
-- Enlaza cuenta de panel (admin_users) con cuenta de juego (users)
-- para permisos de Nexus World Builder sin igualar username.
-- Ejecutar una vez en producción.
-- ============================================================

ALTER TABLE `admin_users`
  ADD COLUMN `linked_game_user_id` BIGINT(20) NULL DEFAULT NULL
    COMMENT 'users.id de la cuenta con la que entras al Nexus/juego' AFTER `username`;

ALTER TABLE `admin_users`
  ADD UNIQUE KEY `uk_admin_linked_game_user` (`linked_game_user_id`);

ALTER TABLE `admin_users`
  ADD CONSTRAINT `fk_admin_users_linked_game_user`
    FOREIGN KEY (`linked_game_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- Ejemplo: owner id=1, jugador en la tienda users.id=6
-- UPDATE admin_users SET linked_game_user_id = 6 WHERE id = 1 AND active = 1;
