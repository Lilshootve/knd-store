<?php
/** Caller sets: $code, $lobbyUrl, $csrfToken */
$code = isset($code) ? $code : '';
$lobbyUrl = $lobbyUrl ?? '/death-roll-lobby.php';
$csrfToken = $csrfToken ?? '';
?>
<div class="center-col lastroll-center-wrap lastroll-page lastroll-game">
  <div style="position:absolute;width:0;height:0;overflow:hidden;clip:rect(0,0,0,0)" aria-hidden="true">
    <button type="button" id="battle-open-mm" tabindex="-1"></button>
    <button type="button" id="qa-change-avatar" tabindex="-1"></button>
    <button type="button" id="qa-inventory" tabindex="-1"></button>
    <button type="button" id="qa-neural-link" tabindex="-1"></button>
    <div id="live-preview"></div>
    <div id="battle-subtext"></div>
  </div>

  <section class="lastroll-game-area" style="min-height:0;padding-top:0;padding-bottom:24px;">
    <div class="container-fluid px-0">
        <div class="row mb-3">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="lastroll-game-brand glow-text mb-1">
                            <i class="fas fa-dice-d20 me-2"></i>KND LastRoll
                            <span class="lastroll-room-code ms-2 small"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></span>
                        </h2>
                        <p class="text-white-50 mb-0 small" style="opacity:0.6;"><?php echo t('dr.game.subtitle_seo', 'Next-gen Death Roll rules — roll until someone hits 1'); ?></p>
                        <p class="text-white-50 mb-0" id="game-status-text"><?php echo t('dr.game.loading', 'Loading game...'); ?></p>
                        <p class="mb-0 mt-1" id="game-kp-info" style="font-size:.85rem; display:none;">
                            <span class="badge bg-dark border border-info" style="font-size:.8rem;">
                                <i class="fas fa-coins me-1" style="color:var(--knd-neon-blue);"></i>
                                Entry: <strong id="game-entry-kp">—</strong> KP
                                &nbsp;|&nbsp; Winner: <strong id="game-payout-kp">—</strong> KP
                            </span>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm lastroll-btn-secondary" id="btn-copy-link" title="<?php echo t('dr.game.copy_link', 'Copy invite link'); ?>">
                            <i class="fas fa-link me-1"></i><?php echo t('dr.game.share', 'Share'); ?>
                        </button>
                        <a href="<?php echo htmlspecialchars($lobbyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm lastroll-btn-secondary lastroll-back-link">
                            <i class="fas fa-arrow-left me-1"></i><?php echo t('dr.game.back_lobby', 'Lobby'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3 lastroll-players-row">
            <div class="col-12">
                <div class="lastroll-players-inline">
                    <div class="lastroll-player-card p1 p-3 rounded lastroll-player-chip" id="player1-card">
                        <div class="lastroll-player-line">
                            <span class="lastroll-player-label small">Player 1</span>
                            <span class="lastroll-player-name fw-bold" id="p1-name">—</span>
                        </div>
                    </div>
                    <div class="lastroll-player-card p2 p-3 rounded lastroll-player-chip" id="player2-card">
                        <div class="lastroll-player-line">
                            <span class="lastroll-player-label small">Player 2</span>
                            <span class="lastroll-player-name fw-bold" id="p2-name"><?php echo t('dr.game.waiting_opponent', 'Waiting...'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 lastroll-game-layout">
            <div class="col-lg-8">
                <div class="lastroll-card glass-card-neon p-3 p-md-4">
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center;" class="mb-3">
                        <div class="lastroll-current-max-label small text-white-50 mb-1"><?php echo t('dr.game.current_max', 'Current Max'); ?></div>
                        <div id="current-max-display" class="lastroll-current-max-value">1000</div>
                        <div id="initial-max-display" class="lastroll-initial-max small text-white-50 mt-1 mb-3">Initial: <span id="initial-max-value">1000</span></div>

                        <div id="dr-dice-wrap" class="dr-hud-card">
                        <svg id="dr-dice-svg" width="120" height="120" viewBox="0 0 120 120" aria-label="dice">
                            <rect x="14" y="14" width="92" height="92" rx="18" class="dr-dice-plate"/>
                            <rect x="20" y="20" width="80" height="80" rx="14" class="dr-dice-glow"/>
                            <text id="dr-dice-num" x="60" y="72" text-anchor="middle" class="dr-dice-text">&mdash;</text>
                            <circle cx="40" cy="40" r="3" class="dr-dice-pip"/>
                            <circle cx="80" cy="60" r="3" class="dr-dice-pip"/>
                            <circle cx="40" cy="80" r="3" class="dr-dice-pip"/>
                        </svg>
                        <div id="dr-dice-status" class="dr-dice-status">Ready</div>
                        </div>
                    </div>

                    <div id="turn-timer-bar" class="text-center mb-3" style="display:none;">
                        <div class="small text-white-50 mb-1"><?php echo t('dr.game.turn_timer', 'Time left'); ?></div>
                        <div class="d-flex justify-content-center align-items-center gap-3">
                            <div id="turn-timer-value" style="font-size: 2rem; font-weight: 900; font-family: 'Orbitron', monospace; color: var(--knd-neon-blue); min-width: 60px;">8</div>
                            <div style="flex: 1; max-width: 200px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                <div id="turn-timer-progress" style="height: 100%; width: 100%; background: var(--knd-neon-blue); transition: width 0.3s linear, background 0.3s;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-3">
                        <div id="turn-info" class="mb-2 text-white-50"></div>
                        <button id="btn-roll" class="btn btn-lg btn-neon-primary px-5 py-3" disabled style="font-size: 1.3rem;">
                            <i class="fas fa-dice me-2"></i><?php echo t('dr.game.roll', 'ROLL!'); ?>
                        </button>
                    </div>

                    <div id="game-over-panel" style="display:none;" class="text-center p-4 rounded" >
                        <div id="game-over-icon" style="font-size: 4rem;"></div>
                        <h3 id="game-over-text" class="mt-2"></h3>
                        <div class="d-flex justify-content-center gap-3 mt-3" id="game-over-actions">
                            <button id="btn-rematch-request" class="btn lastroll-btn-primary">
                                <i class="fas fa-redo me-2"></i><?php echo t('dr.game.rematch', 'Rematch'); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars($lobbyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn lastroll-btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i><?php echo t('dr.game.back_lobby', 'Lobby'); ?>
                            </a>
                        </div>
                        <div id="rematch-status" class="mt-3" style="display:none;"></div>
                    </div>

                    <div id="rematch-offer-panel" class="text-center p-4 mt-3 rounded" style="display:none;background:rgba(37,156,174,0.05);border:2px solid rgba(37,156,174,0.3);">
                        <h4><i class="fas fa-handshake me-2"></i><?php echo t('dr.game.rematch_incoming', 'Rematch Requested!'); ?></h4>
                        <p class="text-white-50" id="rematch-offer-who"></p>
                        <div class="d-flex justify-content-center gap-3">
                            <button id="btn-rematch-accept" class="btn lastroll-btn-primary">
                                <i class="fas fa-check me-2"></i><?php echo t('dr.game.accept', 'Accept'); ?>
                            </button>
                            <button id="btn-rematch-decline" class="btn lastroll-btn-secondary">
                                <i class="fas fa-times me-2"></i><?php echo t('dr.game.decline', 'Decline'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="lastroll-card glass-card-neon p-3 p-md-4 lastroll-history-card">
                    <h5 class="lastroll-history-title mb-3"><i class="fas fa-history me-2"></i><?php echo t('dr.game.history', 'Roll History'); ?></h5>
                    <div id="rolls-list" class="lastroll-rolls-scroll">
                        <p class="text-white-50 small"><?php echo t('dr.game.no_rolls', 'No rolls yet.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </section>
</div>
