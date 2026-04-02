# KND Store — AI Instructions

## CRITICAL RULES — NEVER BREAK THESE
- This is a PHP procedural project. NEVER create Node.js, Python, or any non-PHP backend files.
- NEVER use absolute URLs with domain in chat.php. Always use relative URLs like /api/ollama-proxy.php
- NEVER create package.json, composer.json, or requirements.txt.
- Backend: PHP procedural only, PDO only, MySQL only.
- Frontend: Vanilla JS, Bootstrap 5.3, jQuery 3.6.

## Database connection
Always use getDBConnection() from includes/config.php. Never hardcode credentials.

## API pattern
Every endpoint in /api/*.php must:
1. Start with: header('Content-Type: application/json');
2. Include config: require_once __DIR__ . '/../includes/config.php';
3. Use PDO prepared statements always
4. Never expose DB errors — use error_log() internally
5. Return: {"status":"success","data":{}} or {"status":"error","message":""}

## Authentication
- require_login() for pages
- api_require_login() for API endpoints
- Session via includes/session.php and includes/auth.php

## Economy
- KND Points in points_ledger table
- get_available_points($userId) to read balance

## Systems in production
- Mind Wars: knd_mind_wars_battles, mw_avatars
- Knowledge Duel: knd_quiz_battles, knd_quiz_questions  
- Death Roll: deathroll_games_1v1
- Above/Under: above_under_rolls
- Drops: knd_drops, knd_drop_log
- Avatars: knd_user_avatar, mw_avatar_stats
- XP: knd_user_xp, knd_seasons

## NEXUS WORLD (building now)
- 3D world in Three.js: nexus-city.html
- Districts: tesla, olimpo, casino, agora, base, plaza
- Table: nexus_districts (district_id VARCHAR, memory DECIMAL 0-100, last_updated TIMESTAMP)
- API: /api/nexus-state.php — GET returns all districts, POST updates memory
- Goal: player actions in each district affect that district MEMORY value

## District purposes
- Tesla: quiz, trivia, flags, capitals, intellectual games
- Olimpo: Mind Wars avatar battles
- Casino: Death Roll, Above/Under gambling
- Agora: social, avatars, profiles
- Base: player home, decoratable
- Plaza: leaderboards, top players, records