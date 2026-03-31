<?php
/** @var array $L */
$mwShellGame = $mwShellGame ?? 'mind-wars';
$season = $L['season'] ?? [];
$seasonName = (string) ($season['name'] ?? 'Season');
$secRem = (int) ($season['seconds_remaining'] ?? 0);
$d = intdiv($secRem, 86400);
$h = intdiv($secRem % 86400, 3600);
$m = intdiv($secRem % 3600, 60);
$timerStr = $secRem > 0 ? sprintf('Ends in %dd %dh %dm', $d, $h, $m) : 'Season ended';
$online = (int) ($L['online_hint'] ?? 0);
?>
<div class="right-col col">
  <div class="panel">
    <div class="panel-hdr ph">
      <div class="panel-title ph-title"><div class="pdot pdot-gold"></div> EVENTS</div>
      <button type="button" class="panel-action ph-action" id="events-all-btn">ALL →</button>
    </div>
    <div class="panel-body">
      <div id="event-banner-wrap" style="position:relative">
        <div class="event-banner" id="event-banner-display">
          <div class="eb-tag">LIVE</div>
          <div class="eb-icon" id="eb-icon">🏆</div>
          <div class="eb-content">
            <div class="eb-label" id="eb-label">MIND WARS SEASON</div>
            <div class="eb-title" id="eb-title"><?php echo htmlspecialchars($seasonName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="eb-timer" id="eb-timer"><?php echo htmlspecialchars($timerStr, ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
        <div class="mode-card" style="--mc:#00e8ff" data-open-mm="pvp">
          <div class="mc-ico">⚔️</div>
          <div class="mc-info">
            <div class="mc-mname">Ranked PvP</div>
            <div class="mc-mdesc" id="mc-pvp-online"><?php echo number_format($online); ?> in queue / matched</div>
          </div>
          <div class="mc-badge hot">RANKED</div>
        </div>
        <div class="mode-card" style="--mc:#9b30ff;--mc-rgb:155,48,255" data-open-mm="pve">
          <div class="mc-ico">🤖</div>
          <div class="mc-info">
            <div class="mc-mname">PvE</div>
            <div class="mc-mdesc">vs AI — practice</div>
          </div>
          <div class="mc-badge new">CASUAL</div>
        </div>
        <div class="mode-card" style="--mc:#ffcc00;--mc-rgb:255,204,0" data-open-mm="ranked">
          <div class="mc-ico">🏆</div>
          <div class="mc-info">
            <div class="mc-mname">Season ladder</div>
            <div class="mc-mdesc"><?php echo htmlspecialchars($seasonName, ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="mc-badge ranked">SEASON</div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-hdr ph">
      <div class="panel-title ph-title"><div class="pdot" style="background:var(--m);box-shadow:0 0 6px var(--m)"></div> AVATAR</div>
      <button type="button" class="panel-action ph-action" id="av-panel-change">CHANGE →</button>
    </div>
    <div class="panel-body">
      <div class="av-preview">
        <div class="avp-label">EQUIPPED</div>
        <div class="avp-row" id="avp-slots-row"></div>
        <button type="button" class="avp-btn" id="avp-inspect">⬡ INSPECT AVATAR</button>
      </div>
      <div class="knowledge-row" style="margin-top:8px">
        <span class="knowledge-label">KNOWLEDGE</span>
        <div class="knowledge-orbs-wrap">
          <div class="orbs" id="energy-orbs-display"></div>
          <span class="knowledge-next-txt" id="knowledge-next-label">—</span>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;height:252px">
        <div style="font-family:var(--FD);font-size:9.2px;letter-spacing:3px;color:var(--t3);text-transform:uppercase;margin-bottom:2px">OTHER GAMES</div>
        <a href="/games/knowledge-duel.php" class="mode-card mode-card--link<?php echo $mwShellGame === 'knowledge-duel' ? ' mode-card--active' : ''; ?>" style="--mc:#06b6d4;--mc-rgb:6,182,212">
          <div class="mc-ico">🧠</div>
          <div class="mc-info">
            <div class="mc-mname">Knowledge Duel</div>
            <div class="mc-mdesc">Trivia duel with your avatars</div>
          </div>
          <div class="mc-badge new">DUEL</div>
        </a>
        <a href="/death-roll-lobby.php" class="mode-card mode-card--link<?php echo ($mwShellGame ?? '') === 'lastroll' ? ' mode-card--active' : ''; ?>" style="--mc:#f97316;--mc-rgb:249,115,22">
          <div class="mc-ico">🎲</div>
          <div class="mc-info">
            <div class="mc-mname">KND LastRoll</div>
            <div class="mc-mdesc">1v1 death roll rooms</div>
          </div>
          <div class="mc-badge hot">1V1</div>
        </a>
        <a href="/above-under.php" class="mode-card mode-card--link<?php echo ($mwShellGame ?? '') === 'insight' ? ' mode-card--active' : ''; ?>" style="--mc:#d946ef;--mc-rgb:217,70,239">
          <div class="mc-ico">👁</div>
          <div class="mc-info">
            <div class="mc-mname">KND Insight</div>
            <div class="mc-mdesc">Above / under prediction</div>
          </div>
          <div class="mc-badge ranked">PREDICT</div>
        </a>
        <a href="/squad-arena-v2/squad-selector.php" class="mode-card mode-card--link" style="--mc:#7c3aed;--mc-rgb:124,58,237">
          <div class="mc-ico">👥</div>
          <div class="mc-info">
            <div class="mc-mname">Mind Wars Squad (3v3)</div>
            <div class="mc-mdesc">Squad battle vs AI</div>
          </div>
          <div class="mc-badge new">3V3</div>
        </a>
      </div>
    </div>
  </div>
</div>
