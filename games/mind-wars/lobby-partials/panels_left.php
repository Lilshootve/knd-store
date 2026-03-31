<?php
/** @var array $L */
$season = $L['season'] ?? [];
$ranking = $L['ranking'] ?? [];
$rs = (int) ($ranking['rank_score'] ?? 0);
$wins = (int) ($ranking['wins'] ?? 0);
$losses = (int) ($ranking['losses'] ?? 0);
$wr = (float) ($ranking['win_rate'] ?? 0);
$pos = $ranking['estimated_position'] ?? null;
$posStr = $pos !== null ? '#' . (int) $pos : '—';
$seasonName = (string) ($season['name'] ?? 'Season');
?>
<div class="left-col">
  <div class="rank-widget">
    <div class="rw-top">
      <div>
        <div class="rw-label">Season rank</div>
        <div class="rw-rank" id="rw-rank"><?php echo htmlspecialchars($posStr, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="rw-badge"><?php echo htmlspecialchars($seasonName, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="rw-bar-wrap">
      <div class="rw-bar-meta">
        <span class="rw-bar-lbl">RANK SCORE</span>
        <span class="rw-bar-val" id="rw-bar-val"><?php echo number_format($rs); ?> pts</span>
      </div>
      <div class="rw-bar"><div class="rw-barfill" id="rw-barfill" style="width:<?php echo $rs > 0 ? min(100, (int) (log10($rs + 1) * 25)) : 0; ?>%"></div></div>
    </div>
    <div class="rw-wins">Record: <b><?php echo (int) $wins; ?>W</b> / <b><?php echo (int) $losses; ?>L</b> &nbsp;·&nbsp; WR: <b><?php echo htmlspecialchars(number_format($wr, 1), ENT_QUOTES, 'UTF-8'); ?>%</b></div>
  </div>

  <div class="panel" style="flex:1">
    <div class="panel-hdr">
      <div class="panel-title"><div class="pdot"></div> MISSIONS</div>
      <button type="button" class="panel-action" id="missions-all-btn">ALL →</button>
    </div>
    <div class="panel-body" id="missions-body"></div>
  </div>

  <div class="panel">
    <div class="panel-hdr">
      <div class="panel-title"><div class="pdot" style="background:var(--gold);box-shadow:0 0 6px var(--gold)"></div> TOP PLAYERS</div>
      <button type="button" class="panel-action" id="lb-mini-viewall">VIEW ALL →</button>
    </div>
    <div class="panel-body" id="lb-mini-body"></div>
  </div>
</div>
