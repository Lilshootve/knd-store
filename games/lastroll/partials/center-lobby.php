<?php
/** @var string $username set by death-roll-lobby.php */
$username = $username ?? '';
$myKpBalance = isset($myKpBalance) ? (int) $myKpBalance : 0;
$csrfToken = $csrfToken ?? '';
?>
<div class="center-col lastroll-center-wrap lastroll-page lastroll-game">
  <!-- IDs expected by games/mind-wars/lobby.js (center column stubs when not Mind Wars stage) -->
  <div style="position:absolute;width:0;height:0;overflow:hidden;clip:rect(0,0,0,0)" aria-hidden="true">
    <button type="button" id="battle-open-mm" tabindex="-1"></button>
    <button type="button" id="qa-change-avatar" tabindex="-1"></button>
    <button type="button" id="qa-inventory" tabindex="-1"></button>
    <button type="button" id="qa-neural-link" tabindex="-1"></button>
    <div id="live-preview"></div>
    <div id="battle-subtext"></div>
  </div>

  <section class="lastroll-hero" style="min-height:0;padding-top:0;padding-bottom:24px;">
    <div class="container-fluid px-0">
        <div class="row mb-3">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="lastroll-brand mb-1"><i class="fas fa-dice-d20 me-2"></i>KND LastRoll</h1>
                        <p class="lastroll-subtitle mb-0">
                            <?php echo t('dr.lobby.subtitle_seo', 'A next-gen Death Roll 1v1 experience'); ?>
                            &mdash; <?php echo t('dr.lobby.welcome', 'Welcome'); ?>, <strong><?php echo $username; ?></strong>
                            &mdash; <span id="lobby-active-games">0</span> <?php echo t('dr.lobby.active_games', 'active games'); ?>
                        </p>
                        <p class="mb-0 mt-1" style="font-size:.9rem;">
                            <span id="my-kp-balance" class="lastroll-kp-badge badge <?php echo $myKpBalance > 0 ? '' : 'balance-low'; ?>" style="font-size:.85rem;">
                                <i class="fas fa-wallet me-1"></i>
                                Your KP: <strong><?php echo number_format($myKpBalance); ?></strong>
                            </span>
                        </p>
                    </div>
                    <div class="lastroll-actions d-flex gap-2 flex-wrap">
                        <button type="button" class="btn lastroll-btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create">
                            <i class="fas fa-plus me-1"></i><?php echo t('dr.lobby.create_room', 'Create Room'); ?>
                        </button>
                        <button type="button" class="btn lastroll-btn-secondary" data-bs-toggle="modal" data-bs-target="#modal-join">
                            <i class="fas fa-door-open me-1"></i><?php echo t('dr.lobby.join_code', 'Join by Code'); ?>
                        </button>
                        <button type="button" class="btn lastroll-btn-secondary btn-myrooms" data-bs-toggle="modal" data-bs-target="#modal-myrooms" id="btn-myrooms">
                            <i class="fas fa-th-list me-1"></i><?php echo t('dr.lobby.my_rooms', 'My Rooms'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="lastroll-card lastroll-rules-card glass-card-neon p-3 p-md-4 mt-3 w-100">
                <h5 class="lastroll-card-title mb-3"><i class="fas fa-scroll me-2"></i><?php echo t('dr.lobby.rules_title', 'How to Play'); ?></h5>
                <ol class="lastroll-rules small text-white-50 mb-0 ps-3">
                    <li class="mb-1"><?php echo t('dr.lobby.rule1', 'Create or join a room'); ?></li>
                    <li class="mb-1"><?php echo t('dr.lobby.rule2', 'Player 1 rolls 1-1000'); ?></li>
                    <li class="mb-1"><?php echo t('dr.lobby.rule3', 'Players alternate rolling 1 to last result'); ?></li>
                    <li class="mb-1"><?php echo t('dr.lobby.rule4', 'Whoever rolls 1 loses!'); ?></li>
                </ol>
                <p class="small text-white-50 mt-3 mb-0" style="opacity:0.5; font-style:italic;"><?php echo t('dr.lobby.inspired', 'Inspired by the classic Death Roll format.'); ?></p>
            </div>
            <div class="col-lg-8">
                <div class="lastroll-card glass-card-neon p-3 p-md-4">
                    <h4 class="lastroll-card-title mb-3"><i class="fas fa-globe me-2"></i><?php echo t('dr.lobby.public_rooms', 'Public Rooms'); ?></h4>
                    <div id="lobby-rooms" class="table-responsive">
                        <table class="table table-dark table-hover lastroll-table mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo t('dr.lobby.room_code', 'Code'); ?></th>
                                    <th><?php echo t('dr.lobby.creator', 'Creator'); ?></th>
                                    <th>Max</th>
                                    <th>Entry</th>
                                    <th><?php echo t('dr.lobby.created', 'Created'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="rooms-tbody">
                                <tr><td colspan="6" class="text-center text-white-50"><?php echo t('dr.lobby.loading', 'Loading...'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="lastroll-card glass-card-neon p-3 p-md-4">
                    <h4 class="lastroll-card-title mb-3"><i class="fas fa-users me-2"></i><?php echo t('dr.lobby.online', 'Online'); ?> <span id="online-count" class="badge bg-success ms-1">0</span></h4>
                    <ul id="online-list" class="list-unstyled mb-0" style="max-height: 280px; overflow-y: auto;">
                        <li class="text-white-50"><?php echo t('dr.lobby.loading', 'Loading...'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
  </section>

<div class="modal fade lastroll-modal" id="modal-create" tabindex="-1" aria-labelledby="modal-create-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered lastroll-modal-dialog">
        <div class="modal-content lastroll-modal-content text-light shadow-none">
            <div class="modal-header lastroll-modal-header">
                <h5 class="modal-title" id="modal-create-title"><i class="fas fa-plus-circle me-2"></i><?php echo t('dr.lobby.create_room', 'Create Room'); ?></h5>
                <button type="button" class="btn-close lastroll-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body lastroll-modal-body">
                <form id="form-create-room">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('dr.lobby.visibility', 'Visibility'); ?></label>
                        <select name="visibility" class="form-select">
                            <option value="public"><?php echo t('dr.lobby.public', 'Public'); ?> — <?php echo t('dr.lobby.public_desc', 'visible in lobby'); ?></option>
                            <option value="private"><?php echo t('dr.lobby.private', 'Private'); ?> — <?php echo t('dr.lobby.private_desc', 'invite by code only'); ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('dr.lobby.initial_max', 'Initial Max'); ?></label>
                        <select name="initial_max" class="form-select">
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                            <option value="1000" selected>1,000</option>
                            <option value="2500">2,500</option>
                            <option value="5000">5,000</option>
                            <option value="10000">10,000</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-coins me-1" style="color:var(--knd-neon-blue);"></i>Entry Fee (KP)</label>
                        <select name="entry_kp" id="create-entry-kp" class="form-select">
                            <option value="5">5 KP</option>
                            <option value="10">10 KP</option>
                            <option value="25">25 KP</option>
                            <option value="50">50 KP</option>
                            <option value="100" selected>100 KP</option>
                            <option value="200">200 KP</option>
                            <option value="500">500 KP</option>
                            <option value="1000">1,000 KP</option>
                        </select>
                        <div class="lastroll-modal-payout-hint mt-2 small">
                            <i class="fas fa-trophy me-1"></i><?php echo t('dr.lobby.winner_gets', 'Winner gets'); ?>: <strong id="create-payout-preview">150</strong> KP
                        </div>
                    </div>
                    <button type="submit" class="btn lastroll-btn-primary w-100">
                        <i class="fas fa-rocket me-2"></i><?php echo t('dr.lobby.create_go', 'Create & Wait'); ?>
                    </button>
                </form>
                <div id="create-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade lastroll-modal" id="modal-join" tabindex="-1" aria-labelledby="modal-join-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered lastroll-modal-dialog">
        <div class="modal-content lastroll-modal-content text-light shadow-none">
            <div class="modal-header lastroll-modal-header">
                <h5 class="modal-title" id="modal-join-title"><i class="fas fa-door-open me-2"></i><?php echo t('dr.lobby.join_code', 'Join by Code'); ?></h5>
                <button type="button" class="btn-close lastroll-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body lastroll-modal-body">
                <form id="form-join-code">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('dr.lobby.enter_code', 'Room Code'); ?></label>
                        <input type="text" name="code" class="form-control text-uppercase" required minlength="8" maxlength="8" pattern="[A-Za-z0-9]{8}" placeholder="ABCD1234" style="letter-spacing:3px; font-size:1.2rem; text-align:center;">
                    </div>
                    <button type="submit" class="btn lastroll-btn-secondary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i><?php echo t('dr.lobby.join_btn', 'Join Room'); ?>
                    </button>
                </form>
                <div id="join-alert" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade lastroll-modal" id="modal-myrooms" tabindex="-1" aria-labelledby="modal-myrooms-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg lastroll-modal-dialog">
        <div class="modal-content lastroll-modal-content text-light shadow-none">
            <div class="modal-header lastroll-modal-header">
                <h5 class="modal-title" id="modal-myrooms-title"><i class="fas fa-th-list me-2"></i><?php echo t('dr.lobby.my_rooms', 'My Rooms'); ?> <span id="myrooms-count" class="badge bg-secondary ms-1"></span></h5>
                <button type="button" class="btn-close lastroll-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body lastroll-modal-body lastroll-modal-body-scroll">
                <div id="myrooms-list">
                    <p class="text-white-50 text-center"><?php echo t('dr.lobby.loading', 'Loading...'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
