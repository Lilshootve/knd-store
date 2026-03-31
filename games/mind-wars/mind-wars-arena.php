<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$pdo = getDBConnection();

try {
    require_login();
    $csrfToken = csrf_token();
} catch (Throwable $e) {
    error_log('mind-wars-arena.php bootstrap: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Service temporarily unavailable</h1>';
    echo '<p>Please try again later or contact support.</p>';
    exit;
}

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
$arenaEmbedCssV = file_exists(__DIR__ . '/../../assets/css/arena-embed.css') ? filemtime(__DIR__ . '/../../assets/css/arena-embed.css') : 0;
require_once __DIR__ . '/../../includes/favicon_links.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>MIND WARS — Battle Arena</title>
<?php echo generateFaviconLinks(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo file_exists(__DIR__ . '/../../assets/css/levels.css') ? filemtime(__DIR__ . '/../../assets/css/levels.css') : 0; ?>">
<?php if ($embed): ?>
<link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo $arenaEmbedCssV; ?>">
<?php endif; ?>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --void:#010408;--deep:#030a10;--surface:#05111a;--surface2:#071520;
  --c:#00f0ff;--c2:#00a8bb;--cg:rgba(0,240,255,.14);--cb:rgba(0,240,255,.22);
  --m:#d400ff;--mg:rgba(212,0,255,.14);--mb:rgba(212,0,255,.28);
  --gold:#ffc820;--red:#ff2244;--green:#00ff88;--orange:#ff8800;
  --t1:rgba(220,245,255,.93);--t2:rgba(150,210,230,.6);--t3:rgba(90,160,185,.38);
  --FD:'Orbitron',monospace;--FB:'Rajdhani',sans-serif;--FM:'Share Tech Mono',monospace;
}
html{font-size:18.4px;height:100%}
body{background:var(--void);color:var(--t1);font-family:var(--FB);min-height:100vh;overflow-x:hidden;margin:0;width:100%}

/* ── BACKGROUND ── */
#bg{position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 100% 55% at 50% -5%,rgba(0,120,200,.15) 0%,transparent 55%),
    radial-gradient(ellipse 60% 45% at 8% 60%,rgba(0,240,255,.07) 0%,transparent 50%),
    radial-gradient(ellipse 60% 45% at 92% 60%,rgba(212,0,255,.06) 0%,transparent 50%),
    radial-gradient(ellipse 40% 30% at 50% 100%,rgba(0,60,100,.2) 0%,transparent 60%),
    var(--void);
  pointer-events:none}
#bg::before{content:'';position:absolute;inset:0;
  background-image:
    radial-gradient(circle 1px at 15% 20%,rgba(0,240,255,.55) 0%,transparent 1px),
    radial-gradient(circle 1px at 72% 10%,rgba(0,240,255,.35) 0%,transparent 1px),
    radial-gradient(circle 1px at 88% 35%,rgba(212,0,255,.45) 0%,transparent 1px),
    radial-gradient(circle 1px at 30% 75%,rgba(0,240,255,.35) 0%,transparent 1px),
    radial-gradient(circle 1px at 55% 90%,rgba(212,0,255,.35) 0%,transparent 1px),
    radial-gradient(circle 1px at 5% 55%,rgba(255,200,32,.35) 0%,transparent 1px),
    radial-gradient(circle 2px at 95% 80%,rgba(0,240,255,.28) 0%,transparent 2px),
    radial-gradient(circle 1px at 42% 42%,rgba(255,255,255,.2) 0%,transparent 1px);
  animation:twinkle 6s ease-in-out infinite alternate}
@keyframes twinkle{0%{opacity:.5}100%{opacity:1}}
#bg::after{content:'';position:absolute;inset:0;
  background-image:linear-gradient(rgba(0,240,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(0,240,255,.018) 1px,transparent 1px);
  background-size:52px 52px}

/* ── FLOOR ── */
#floor{position:fixed;bottom:0;left:0;right:0;height:50vh;z-index:0;pointer-events:none;overflow:hidden}
#floor::before{content:'';position:absolute;bottom:0;left:-50%;width:200%;height:100%;
  background:
    repeating-linear-gradient(90deg,transparent,transparent calc(6.25% - 1px),rgba(0,240,255,.045) calc(6.25%),transparent calc(6.25% + 1px)),
    repeating-linear-gradient(0deg,transparent,transparent calc(12% - 1px),rgba(0,240,255,.03) calc(12%),transparent calc(12% + 1px));
  transform:perspective(380px) rotateX(58deg);transform-origin:bottom center}
#floor::after{content:'';position:absolute;bottom:49%;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,var(--c),var(--m),var(--c),transparent);
  opacity:.25;box-shadow:0 0 40px 4px rgba(0,240,255,.15)}

/* ── SCREEN FLASH ── */
#sf{position:fixed;inset:0;z-index:500;pointer-events:none;opacity:0;background:rgba(255,30,50,.14);transition:opacity .06s}
#sf.pop{opacity:1;transition:none}

/* ── SURRENDER BUTTON ── */
#surrender-btn,.surrender-btn-arena{background:transparent;border:1px solid rgba(255,34,68,.3);color:rgba(255,100,120,.7);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;padding:5px 14px;cursor:pointer;transition:all .22s}
#surrender-btn:hover,.surrender-btn-arena:hover{border-color:var(--red);color:var(--red);box-shadow:0 0 14px rgba(255,34,68,.25)}
.arena-surrender-row{display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:14px;margin:12px 0 6px}
.arena-sfx-toggle{background:transparent;border:1px solid rgba(0,240,255,.25);color:rgba(150,210,230,.75);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;padding:5px 14px;cursor:pointer;transition:all .22s;min-width:44px}
.arena-sfx-toggle:hover{border-color:var(--c);color:var(--c);box-shadow:0 0 14px rgba(0,240,255,.18)}
.arena-sfx-toggle.muted{border-color:rgba(255,255,255,.12);color:var(--t3)}
.arena-sfx-toggle.muted:hover{border-color:var(--t2);color:var(--t2)}

/* ── END-GAME OVERLAY (squad-arena-v2 / battlefield.html style — no trophy/skull icons) ── */
#endgame{position:fixed;inset:0;z-index:600;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .5s}
#endgame.show{opacity:1;pointer-events:all}
#endgame-bg{position:absolute;inset:0;backdrop-filter:blur(22px) saturate(1.5) brightness(.7);transition:background .6s}
#endgame.win #endgame-bg{background:radial-gradient(ellipse at 50% 40%,rgba(0,40,60,.85),rgba(0,5,12,.95))}
#endgame.lose #endgame-bg{background:radial-gradient(ellipse at 50% 40%,rgba(40,0,20,.85),rgba(8,0,5,.95))}
#endgame.draw #endgame-bg{background:radial-gradient(ellipse at 50% 40%,rgba(35,30,10,.88),rgba(8,6,2,.95))}
#endgame.surrender #endgame-bg{background:radial-gradient(ellipse at 50% 40%,rgba(35,8,12,.88),rgba(12,2,6,.95))}
#endgame-confetti{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:1}
.confetti-p{position:absolute;width:6px;height:10px;opacity:0;animation:mwEgConfetti var(--dur,3s) ease-in var(--delay,0s) forwards}
@keyframes mwEgConfetti{0%{opacity:1;transform:translateY(-20px) rotate(0deg) scale(1)}50%{opacity:1}100%{opacity:0;transform:translateY(100vh) rotate(var(--rot,720deg)) scale(.3)}}
#endgame-panel{position:relative;z-index:2;width:min(94vw,580px);display:flex;flex-direction:column;align-items:center;text-align:center;gap:0}
#endgame{--egc:var(--c)}
.eg-tl{width:100%;height:3px;background:var(--egc,var(--c));box-shadow:0 0 30px var(--egc,var(--c)),0 0 60px var(--egc,var(--c));transform:scaleX(0)}
.eg-tl.anim{animation:mwEgLine .7s cubic-bezier(.2,.8,.2,1) forwards}
@keyframes mwEgLine{to{transform:scaleX(1)}}
.eg-box{width:100%;background:linear-gradient(180deg,rgba(5,17,26,.95),rgba(3,10,18,.98));border:1px solid rgba(255,255,255,.06);border-top:none;border-bottom:none;padding:32px 36px 28px;display:flex;flex-direction:column;align-items:center;gap:0;position:relative;
  clip-path:polygon(0 0,100% 0,100% calc(100% - 16px),calc(100% - 16px) 100%,16px 100%,0 calc(100% - 16px))}
#endgame.win .eg-box{border-color:rgba(0,240,255,.12)}
#endgame.lose .eg-box{border-color:rgba(212,0,255,.12)}
#endgame.draw .eg-box{border-color:rgba(255,200,32,.12)}
#endgame.surrender .eg-box{border-color:rgba(255,34,68,.12)}
.eg-res{font-family:var(--FD);font-weight:900;letter-spacing:8px;line-height:1;color:var(--egc,var(--c));margin-top:4px;text-transform:uppercase;
  text-shadow:0 0 30px var(--egc,var(--c)),0 0 80px var(--egc,var(--c));font-size:clamp(42px,8vw,76px);
  opacity:0;transform:translateY(24px) scaleX(.7)}
.eg-res.anim{animation:mwEgTitleSlam .5s cubic-bezier(.15,1,.3,1) forwards}
@keyframes mwEgTitleSlam{to{opacity:1;transform:translateY(0) scaleX(1)}}
.eg-sub{font-family:var(--FM);font-size:11px;letter-spacing:3px;color:var(--t2);margin-top:8px;opacity:0;transform:translateY(8px)}
.eg-sub.anim{animation:mwEgFade .4s ease forwards}
@keyframes mwEgFade{to{opacity:1;transform:translateY(0)}}
.eg-div{width:80%;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);margin:18px 0;opacity:0}
.eg-div.anim{animation:mwEgFade .3s ease forwards}
.eg-sr{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;width:100%;margin-top:4px}
.eg-si{text-align:center;opacity:0;transform:translateY(12px)}
.eg-si.anim{animation:mwEgStatPop .5s cubic-bezier(.2,1,.3,1) forwards}
@keyframes mwEgStatPop{to{opacity:1;transform:translateY(0)}}
.eg-sv{font-family:var(--FD);font-size:26px;font-weight:700;color:var(--egc,var(--c));text-shadow:0 0 14px var(--egc,var(--c))}
.eg-sl{font-family:var(--FM);font-size:7px;letter-spacing:2.5px;color:var(--t3);text-transform:uppercase;margin-top:2px}
.eg-xp-wrap{width:85%;margin-top:16px;opacity:0;transform:translateY(8px)}
.eg-xp-wrap.anim{animation:mwEgFade .4s ease forwards}
.eg-xp-label{display:flex;justify-content:space-between;font-family:var(--FM);font-size:8px;letter-spacing:2px;color:var(--t3);margin-bottom:4px}
.eg-xp-track{width:100%;height:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:1px;overflow:hidden;position:relative}
.eg-xp-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--c),#00ffaa);box-shadow:0 0 10px var(--c);border-radius:1px;transition:width 1.5s cubic-bezier(.2,.8,.2,1)}
.eg-xp-fill.lose{background:linear-gradient(90deg,var(--m),#ff6688);box-shadow:0 0 10px var(--m)}
.eg-rw{display:flex;gap:18px;margin-top:16px;flex-wrap:wrap;justify-content:center}
.eg-ri{display:flex;flex-direction:column;align-items:center;gap:2px;font-family:var(--FM);font-size:13px;opacity:0;transform:scale(.5) translateY(10px)}
.eg-ri.anim{animation:mwEgRewardPop .5s cubic-bezier(.15,1.2,.3,1) forwards}
@keyframes mwEgRewardPop{to{opacity:1;transform:scale(1) translateY(0)}}
.eg-rv{font-weight:700;font-family:var(--FD);font-size:15px}
.eg-rv.xp{color:var(--c)}.eg-rv.rk{color:var(--gold)}.eg-rv.ke{color:var(--green)}
.eg-ri-lbl{font-family:var(--FM);font-size:7px;letter-spacing:2px;color:var(--t3);text-transform:uppercase}
.eg-q{font-family:var(--FB);font-size:14px;font-style:italic;color:var(--t2);line-height:1.6;max-width:380px;margin-top:18px;min-height:22px;opacity:0}
.eg-q.anim{animation:mwEgFade .3s ease forwards}
.eg-btns{display:flex;gap:12px;margin-top:20px;opacity:0;transform:translateY(10px)}
.eg-btns.anim{animation:mwEgFade .4s ease forwards}
.eg-btn{background:transparent;border:1px solid var(--egc,var(--c));color:var(--egc,var(--c));font-family:var(--FD);font-size:10px;font-weight:700;letter-spacing:3px;padding:12px 28px;cursor:pointer;text-transform:uppercase;transition:all .25s;position:relative;overflow:hidden;
  clip-path:polygon(6px 0%,100% 0%,100% calc(100% - 6px),calc(100% - 6px) 100%,0 100%,0 6px)}
.eg-btn::before{content:'';position:absolute;inset:0;background:var(--egc,var(--c));opacity:0;transition:opacity .22s}
.eg-btn:hover::before{opacity:.12}
.eg-btn:hover{box-shadow:0 0 28px var(--egc,var(--c));letter-spacing:4px}
.eg-btn.sec{border-color:rgba(255,255,255,.12);color:var(--t2)}
.eg-btn.sec:hover{border-color:var(--t2);box-shadow:0 0 12px rgba(255,255,255,.1)}
.eg-bl{width:100%;height:3px;background:var(--egc,var(--c));box-shadow:0 0 30px var(--egc,var(--c)),0 0 60px var(--egc,var(--c));transform:scaleX(0)}
.eg-bl.anim{animation:mwEgLine .7s cubic-bezier(.2,.8,.2,1) forwards}

/* ── SURRENDER CONFIRM ── */
#surrender-confirm{position:fixed;inset:0;z-index:550;display:flex;align-items:center;justify-content:center;background:rgba(1,4,8,.88);backdrop-filter:blur(12px);opacity:0;pointer-events:none;transition:opacity .2s}
#surrender-confirm.show{opacity:1;pointer-events:all}
.sc-box{width:min(90vw,400px);background:var(--surface);border:1px solid rgba(255,34,68,.35);padding:32px 32px 28px;display:flex;flex-direction:column;align-items:center;gap:16px;clip-path:polygon(0 10px,10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%);box-shadow:0 0 60px rgba(255,34,68,.1);position:relative}
.sc-box::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--red),transparent)}
.sc-ttl{font-family:var(--FD);font-size:12.65px;font-weight:700;letter-spacing:3px;color:var(--red);text-shadow:0 0 12px rgba(255,34,68,.5)}
.sc-txt{font-family:var(--FB);font-size:15px;color:var(--t2);text-align:center;line-height:1.6}
.sc-btns{display:flex;gap:10px}
.sc-yes{background:rgba(255,34,68,.1);border:1px solid var(--red);color:var(--red);font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;padding:9px 20px;cursor:pointer;transition:all .2s}
.sc-yes:hover{background:rgba(255,34,68,.2);box-shadow:0 0 16px rgba(255,34,68,.3)}
.sc-no{background:transparent;border:1px solid rgba(255,255,255,.12);color:var(--t2);font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;padding:9px 20px;cursor:pointer;transition:all .2s}
.sc-no:hover{border-color:var(--t2);color:var(--t1)}

/* ── NAV ── */
.nav{position:relative;z-index:50;display:flex;align-items:center;justify-content:space-between;padding:0 36px;height:56px;background:rgba(1,4,8,.9);border-bottom:1px solid rgba(0,240,255,.1);backdrop-filter:blur(24px)}
.nav-logo{font-family:var(--FD);font-size:17.25px;font-weight:900;letter-spacing:5px;color:var(--c);text-shadow:0 0 20px rgba(0,240,255,.55);display:flex;align-items:center;gap:9px}
.nav-logo svg{width:20px;height:20px;animation:logoHex 8s linear infinite}
@keyframes logoHex{0%,100%{filter:drop-shadow(0 0 6px var(--c))}50%{filter:drop-shadow(0 0 16px var(--m));color:var(--m)}}
.nav-center{font-family:var(--FD);font-size:12.65px;font-weight:700;letter-spacing:3px;color:var(--t2);text-transform:uppercase}
.nav-center em{font-style:normal;color:var(--c)}
.nav-right{font-family:var(--FM);font-size:11.5px;letter-spacing:1.5px;color:var(--t3);display:flex;align-items:center;gap:14px}
.nav-right b{color:var(--gold)}

/* ── ARENA ── */
.arena{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 20px;gap:18px;min-height:calc(100vh - 56px);width:100%;box-sizing:border-box}

/* round strip */
.bhdr{display:flex;align-items:center;justify-content:center;gap:20px;width:100%;max-width:1060px;margin:0 auto}
.rpill{font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:3px;padding:5px 18px;border:1px solid rgba(0,240,255,.3);color:var(--c);background:rgba(0,240,255,.05);position:relative}
.rpill::before,.rpill::after{content:'';position:absolute;top:50%;transform:translateY(-50%);height:1px;width:32px;background:linear-gradient(90deg,transparent,var(--c))}
.rpill::before{right:100%;margin-right:8px}
.rpill::after{left:100%;margin-left:8px;transform:translateY(-50%) scaleX(-1)}
.tpill{font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;padding:5px 14px;border:1px solid rgba(0,240,255,.15);color:var(--t2);background:rgba(0,240,255,.03);transition:all .3s}
.tpill.pt{color:var(--c);border-color:rgba(0,240,255,.4);background:rgba(0,240,255,.07);box-shadow:0 0 16px rgba(0,240,255,.12)}
.tpill.et{color:var(--m);border-color:rgba(212,0,255,.4);background:rgba(212,0,255,.06);box-shadow:0 0 16px rgba(212,0,255,.12)}

/* ── STAGE ── */
.stage{display:flex;align-items:flex-start;justify-content:center;width:100%;max-width:1060px;gap:0;margin:0 auto}
.vs-col{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0 24px;flex-shrink:0;align-self:center;margin-top:-20px;gap:10px}
.vs-glyph{font-family:var(--FD);font-size:55.2px;font-weight:900;letter-spacing:6px;background:linear-gradient(175deg,var(--c) 0%,rgba(0,240,255,.4) 40%,var(--m) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;filter:drop-shadow(0 0 24px rgba(0,240,255,.4));animation:vsBeat 2.5s ease-in-out infinite;line-height:1}
@keyframes vsBeat{0%,100%{filter:drop-shadow(0 0 20px rgba(0,240,255,.35))}50%{filter:drop-shadow(0 0 40px rgba(212,0,255,.55))}}
.vs-sep{width:1px;height:44px;background:linear-gradient(180deg,transparent,var(--cb),transparent)}

/* ── BATTLE CARD ── */
.bcard{width:310px;flex-shrink:0;background:linear-gradient(180deg,var(--surface) 0%,var(--surface2) 100%);border:1px solid var(--cb);position:relative;overflow:visible;transition:border-color .35s,box-shadow .35s;clip-path:polygon(0 12px,12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%)}
.bcard::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--rc,var(--c));z-index:3;box-shadow:0 0 18px var(--rc,var(--c)),0 0 50px rgba(0,240,255,.12)}

/* active turn */
.bcard.aturn{border-color:rgba(0,240,255,.5);box-shadow:0 0 0 1px rgba(0,240,255,.12),0 0 55px rgba(0,240,255,.14),0 0 110px rgba(0,240,255,.05),inset 0 0 50px rgba(0,240,255,.03);animation:cardAlive 2s ease-in-out infinite}
.bcard.aturn.enemy{border-color:rgba(212,0,255,.45);box-shadow:0 0 0 1px rgba(212,0,255,.1),0 0 55px rgba(212,0,255,.12),inset 0 0 50px rgba(212,0,255,.025);animation:cardAliveE 2s ease-in-out infinite}
@keyframes cardAlive{0%,100%{box-shadow:0 0 40px rgba(0,240,255,.1),0 0 0 1px rgba(0,240,255,.1)}50%{box-shadow:0 0 65px rgba(0,240,255,.2),0 0 0 1px rgba(0,240,255,.24)}}
@keyframes cardAliveE{0%,100%{box-shadow:0 0 40px rgba(212,0,255,.08),0 0 0 1px rgba(212,0,255,.1)}50%{box-shadow:0 0 65px rgba(212,0,255,.18),0 0 0 1px rgba(212,0,255,.22)}}

/* damage */
.bcard.shaking{animation:shake .44s cubic-bezier(.36,.07,.19,.97)!important}
@keyframes shake{0%,100%{transform:translateX(0) rotate(0)}15%{transform:translateX(-9px) rotate(-.5deg)}30%{transform:translateX(9px) rotate(.5deg)}45%{transform:translateX(-6px) rotate(-.2deg)}60%{transform:translateX(6px) rotate(.2deg)}75%{transform:translateX(-3px)}90%{transform:translateX(3px)}}
.bcard.healing{animation:healGlow .65s ease!important}
@keyframes healGlow{0%,100%{box-shadow:none}50%{box-shadow:0 0 65px rgba(0,255,136,.3),inset 0 0 40px rgba(0,255,136,.08)}}
.bcard.dead{animation:holoDie 1.4s ease forwards!important}
@keyframes holoDie{0%{opacity:1;filter:brightness(1) saturate(1)}20%{filter:brightness(2.8) saturate(4) hue-rotate(30deg)}50%{opacity:.55;filter:brightness(.5) blur(2px) saturate(0)}100%{opacity:.1;filter:brightness(.12) blur(5px) saturate(0)}}
.bcard.enemy.card-activation{animation:enemyCardActivation .48s cubic-bezier(.2,.8,.2,1) both}
@keyframes enemyCardActivation{
  0%{transform:scale(.8);filter:brightness(1.45) drop-shadow(0 0 0 rgba(212,0,255,0));box-shadow:0 0 0 1px rgba(212,0,255,.2),0 0 0 rgba(212,0,255,0)}
  45%{transform:scale(1.03);filter:brightness(1.2) drop-shadow(0 0 24px rgba(212,0,255,.45));box-shadow:0 0 0 1px rgba(212,0,255,.45),0 0 48px rgba(212,0,255,.3)}
  100%{transform:scale(1);filter:brightness(1) drop-shadow(0 0 0 rgba(212,0,255,0));box-shadow:0 0 0 1px rgba(212,0,255,.14),0 0 18px rgba(212,0,255,.12)}
}
.bcard.player-activation{animation:playerCardActivation .48s cubic-bezier(.2,.8,.2,1) both}
@keyframes playerCardActivation{
  0%{transform:scale(.8);filter:brightness(1.45) drop-shadow(0 0 0 rgba(0,240,255,0));box-shadow:0 0 0 1px rgba(0,240,255,.2),0 0 0 rgba(0,240,255,0)}
  45%{transform:scale(1.03);filter:brightness(1.2) drop-shadow(0 0 24px rgba(0,240,255,.45));box-shadow:0 0 0 1px rgba(0,240,255,.45),0 0 48px rgba(0,240,255,.3)}
  100%{transform:scale(1);filter:brightness(1) drop-shadow(0 0 0 rgba(0,240,255,0));box-shadow:0 0 0 1px rgba(0,240,255,.14),0 0 18px rgba(0,240,255,.12)}
}
.redflash{position:absolute;inset:0;z-index:20;background:rgba(255,20,50,.18);opacity:0;pointer-events:none;transition:opacity .05s}
.bcard.hit .redflash{opacity:1}
.bcard.flicker .cholo::after{content:'';position:absolute;inset:0;z-index:5;pointer-events:none;background:rgba(0,240,255,.07);animation:flick .3s steps(3)}
@keyframes flick{0%{opacity:1}33%{opacity:0}66%{opacity:.7}100%{opacity:0}}

/* special ready outer ring */
.bcard.sready{box-shadow:0 0 0 2px rgba(255,200,32,.4),0 0 30px rgba(255,200,32,.15)!important;animation:srCard 1.1s ease-in-out infinite!important}
@keyframes srCard{0%,100%{box-shadow:0 0 0 2px rgba(255,200,32,.35),0 0 20px rgba(255,200,32,.12)}50%{box-shadow:0 0 0 2px rgba(255,200,32,.7),0 0 40px rgba(255,200,32,.3)}}

/* ── HOLO VIEWPORT ── */
.cholo{position:relative;height:230px;background:radial-gradient(ellipse 75% 65% at 50% 35%,rgba(0,70,120,.22) 0%,transparent 70%),linear-gradient(180deg,rgba(0,240,255,.025) 0%,transparent 40%),var(--surface);overflow:hidden;border-bottom:1px solid rgba(0,240,255,.07);display:flex;align-items:flex-end;justify-content:center}
.cholo::before{content:'';position:absolute;inset:0;z-index:4;pointer-events:none;background:repeating-linear-gradient(180deg,transparent,transparent 3px,rgba(0,0,0,.09) 3px,rgba(0,0,0,.09) 4px)}

/* beam */
.hbeam{position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:2px;height:80%;z-index:1;background:linear-gradient(0deg,rgba(0,240,255,.38),transparent);filter:blur(2px);animation:beamP 2.2s ease-in-out infinite}
.bcard.enemy .hbeam{background:linear-gradient(0deg,rgba(212,0,255,.32),transparent)}
@keyframes beamP{0%,100%{opacity:.4;height:75%}50%{opacity:.85;height:86%}}

/* platform rings */
.hplat{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);width:100px;height:14px;z-index:2}
.hr1{position:absolute;inset:0;border-radius:50%;border:1px solid rgba(0,240,255,.55);box-shadow:0 0 14px rgba(0,240,255,.28),inset 0 0 8px rgba(0,240,255,.1);animation:rBreath 2.4s ease-in-out infinite}
.hr2{position:absolute;inset:4px;border-radius:50%;border:1px solid rgba(0,240,255,.2);animation:rBreath 2.4s ease-in-out infinite .4s}
.hdot{position:absolute;inset:0;border-radius:50%;background:radial-gradient(ellipse at center,rgba(0,240,255,.18) 0%,transparent 70%);animation:rBreath 2.4s ease-in-out infinite .2s}
.bcard.enemy .hr1{border-color:rgba(212,0,255,.5);box-shadow:0 0 14px rgba(212,0,255,.22),inset 0 0 8px rgba(212,0,255,.08)}
.bcard.enemy .hr2{border-color:rgba(212,0,255,.18)}
.bcard.enemy .hdot{background:radial-gradient(ellipse at center,rgba(212,0,255,.15) 0%,transparent 70%)}
@keyframes rBreath{0%,100%{opacity:.45;transform:scaleX(1)}50%{opacity:.9;transform:scaleX(1.08)}}

/* avatar figure */
.avfig{position:relative;z-index:3;width:120px;height:186px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:10px;animation:avFloat 4.2s ease-in-out infinite}
@keyframes avFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
.avfig img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 0 22px rgba(0,240,255,.45))}
.bcard.enemy .avfig img{filter:drop-shadow(0 0 22px rgba(212,0,255,.5))}

/* silhouette */
.avsil{display:flex;flex-direction:column;align-items:center;gap:3px;position:relative;width:100%;height:100%;justify-content:flex-end;padding-bottom:2px}
.sh{width:46px;height:46px;border-radius:50%;background:linear-gradient(145deg,rgba(0,240,255,.25),rgba(0,240,255,.04));border:1px solid rgba(0,240,255,.4);box-shadow:0 0 24px rgba(0,240,255,.2),inset 0 0 14px rgba(0,240,255,.1);position:relative;flex-shrink:0}
.sh::before{content:'';position:absolute;top:55%;left:20%;right:20%;height:1px;background:rgba(0,240,255,.3)}
.sh::after{content:'';position:absolute;top:35%;left:30%;right:30%;height:1px;background:rgba(0,240,255,.2);box-shadow:0 8px 0 rgba(0,240,255,.2)}
.sn{width:16px;height:12px;flex-shrink:0;background:rgba(0,240,255,.14);border:1px solid rgba(0,240,255,.22)}
.st{width:70px;height:90px;flex-shrink:0;clip-path:polygon(15% 0%,85% 0%,100% 100%,0% 100%);background:linear-gradient(180deg,rgba(0,240,255,.2),rgba(0,240,255,.06));border:1px solid rgba(0,240,255,.24);box-shadow:inset 0 0 24px rgba(0,240,255,.07);position:relative}
.st::before{content:'⬡';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:25.3px;color:rgba(0,240,255,.2);text-shadow:0 0 8px rgba(0,240,255,.3)}
.sl{width:64px;height:46px;flex-shrink:0;display:flex;gap:4px}
.slg{flex:1;background:linear-gradient(180deg,rgba(0,240,255,.18),rgba(0,240,255,.05));border:1px solid rgba(0,240,255,.18);clip-path:polygon(5% 0%,95% 0%,88% 100%,12% 100%)}
/* enemy tints */
.bcard.enemy .sh{border-color:rgba(212,0,255,.38);background:linear-gradient(145deg,rgba(212,0,255,.22),rgba(212,0,255,.04));box-shadow:0 0 24px rgba(212,0,255,.18),inset 0 0 14px rgba(212,0,255,.08)}
.bcard.enemy .sh::before,.bcard.enemy .sh::after{background:rgba(212,0,255,.25)}
.bcard.enemy .sn{background:rgba(212,0,255,.12);border-color:rgba(212,0,255,.2)}
.bcard.enemy .st{background:linear-gradient(180deg,rgba(212,0,255,.18),rgba(212,0,255,.05));border-color:rgba(212,0,255,.22)}
.bcard.enemy .st::before{color:rgba(212,0,255,.2);text-shadow:0 0 8px rgba(212,0,255,.3)}
.bcard.enemy .slg{background:linear-gradient(180deg,rgba(212,0,255,.16),rgba(212,0,255,.04));border-color:rgba(212,0,255,.16)}

.vid{position:absolute;top:9px;left:11px;z-index:6;font-family:var(--FM);font-size:9.2px;letter-spacing:1px;color:var(--t3)}
.vrar{position:absolute;top:9px;right:11px;z-index:6;font-family:var(--FD);font-size:8.62px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border:1px solid var(--rc,var(--c));color:var(--rc,var(--c));background:rgba(0,0,0,.62);text-shadow:0 0 8px var(--rc,var(--c))}
.vlvl{position:absolute;bottom:10px;right:11px;z-index:6;font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;color:var(--rc,var(--c));background:rgba(0,0,0,.62);padding:3px 8px;border:1px solid var(--rc,var(--c));text-shadow:0 0 8px var(--rc,var(--c))}

/* intent */
.itag{position:absolute;top:-38px;left:50%;transform:translateX(-50%);white-space:nowrap;z-index:10;font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;padding:5px 14px;background:rgba(2,5,10,.92);border:1px solid rgba(212,0,255,.4);color:var(--m);backdrop-filter:blur(8px);box-shadow:0 0 20px rgba(212,0,255,.18)}
.itag::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:rgba(212,0,255,.4)}

/* ── CARD BODY ── */
.cbody{padding:14px 16px 16px;display:flex;flex-direction:column;gap:11px;background:var(--surface2);position:relative;z-index:2}
.cnrow{display:flex;align-items:baseline;justify-content:space-between}
.cname{font-family:var(--FD);font-size:19.55px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--t1);line-height:1}
.cclass{font-family:var(--FM);font-size:10.35px;letter-spacing:2px;text-transform:uppercase;color:var(--t3)}

/* HP */
.hpw{display:flex;flex-direction:column;gap:4px}
.hpm{display:flex;justify-content:space-between;align-items:center}
.hpl{font-family:var(--FD);font-size:9.2px;letter-spacing:2.5px;color:var(--t3)}
.hpn{font-family:var(--FM);font-size:13.8px;color:var(--t1)}
.hpt{height:8px;background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden;position:relative;border:1px solid rgba(0,240,255,.08)}
.hpf{height:100%;border-radius:4px;background:linear-gradient(90deg,#00cc55,#00ff99);box-shadow:0 0 10px rgba(0,255,136,.45);transition:width .65s cubic-bezier(.2,.8,.2,1);position:relative}
.hpf::after{content:'';position:absolute;top:0;right:0;bottom:0;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);animation:hpShim 2.5s ease-in-out infinite}
@keyframes hpShim{0%,100%{opacity:0;transform:translateX(-100%)}50%{opacity:1;transform:translateX(200%)}}
.hpf.warn{background:linear-gradient(90deg,#ff6600,#ff9900);box-shadow:0 0 10px rgba(255,120,0,.45)}
.hpf.crit{background:linear-gradient(90deg,#cc0022,#ff2244);box-shadow:0 0 10px rgba(255,30,60,.5);animation:critF .9s ease-in-out infinite}
@keyframes critF{0%,100%{box-shadow:0 0 8px rgba(255,30,60,.4)}50%{box-shadow:0 0 20px rgba(255,30,60,.75)}}

/* ENERGY */
.enw{display:flex;flex-direction:column;gap:5px}
.enm{display:flex;justify-content:space-between;align-items:center}
.enl{font-family:var(--FD);font-size:9.2px;letter-spacing:2.5px;color:var(--t3)}
.env{font-family:var(--FM);font-size:11.5px;color:var(--orange)}
.enorbs{display:flex;gap:6px;align-items:center}
.orb{width:16px;height:16px;border-radius:50%;background:rgba(255,136,0,.06);border:1px solid rgba(255,136,0,.2);transition:all .3s;position:relative}
.orb.on{background:radial-gradient(circle at 35% 35%,rgba(255,210,80,.95),rgba(255,100,0,.85));border-color:var(--orange);box-shadow:0 0 10px rgba(255,136,0,.6),0 0 22px rgba(255,136,0,.2),inset 0 0 5px rgba(255,230,100,.3);animation:orbPop .3s cubic-bezier(.2,.8,.2,1)}
@keyframes orbPop{0%{transform:scale(.6)}60%{transform:scale(1.2)}100%{transform:scale(1)}}

/* STATS */
.sgrid{display:grid;grid-template-columns:1fr 1fr;gap:6px 12px}
.si{display:flex;flex-direction:column;gap:3px}
.sir{display:flex;justify-content:space-between;align-items:center}
.sik{font-family:var(--FD);font-size:8.62px;letter-spacing:2px;text-transform:uppercase;color:var(--t3)}
.siv{font-family:var(--FM);font-size:13.8px;color:var(--t1)}
.sitr{height:2px;background:rgba(255,255,255,.06);border-radius:1px;overflow:hidden}
.sif{height:100%;border-radius:1px;background:var(--sc,var(--c));box-shadow:0 0 4px var(--sc,var(--c))}

/* STATUS */
.sbrow{display:flex;gap:5px;flex-wrap:wrap;min-height:20px;align-items:center}
.sbadge{font-family:var(--FD);font-size:8.62px;font-weight:700;letter-spacing:1px;padding:2px 8px;border-radius:2px;text-transform:uppercase}
.sbadge.buff{border:1px solid rgba(0,255,136,.4);color:var(--green);background:rgba(0,255,136,.07)}
.sbadge.debuff{border:1px solid rgba(255,30,60,.4);color:var(--red);background:rgba(255,30,60,.07)}
.sbadge.shield{border:1px solid rgba(0,200,255,.4);color:var(--c);background:rgba(0,200,255,.07)}

/* FOOTER */
.cfooter{padding:9px 16px 13px;background:var(--surface2);border-top:1px solid rgba(0,240,255,.06);display:flex;justify-content:space-between;align-items:center;position:relative;z-index:2}
.abtn2{background:transparent;border:1px solid var(--cb);color:var(--c);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:6px 14px;cursor:pointer;transition:all .2s}
.abtn2:hover{background:var(--cg);border-color:var(--c);box-shadow:0 0 14px rgba(0,240,255,.2);transform:translateY(-1px)}
.bcard.enemy .abtn2{border-color:var(--mb);color:var(--m)}
.bcard.enemy .abtn2:hover{background:var(--mg);box-shadow:0 0 14px rgba(212,0,255,.2)}
.cxp{font-family:var(--FM);font-size:9.2px;letter-spacing:1.5px;color:var(--t3)}

/* ── FLOAT NUMBERS ── */
.fnum{position:fixed;z-index:1000;pointer-events:none;font-family:var(--FD);font-weight:900;letter-spacing:2px;text-shadow:0 0 12px currentColor;animation:fUp 1.15s ease-out forwards}
.fnum.dmg{color:var(--red);font-size:37px}
.fnum.heal{color:var(--green);font-size:34.38px}
.fnum.miss{color:var(--t2);font-size:18.4px;font-family:var(--FM);letter-spacing:3px}
.fnum.crit{color:var(--gold);font-size:43.7px;animation:fUpCrit 1.2s ease-out forwards}
.fnum.block{color:var(--c);font-size:18.4px;font-family:var(--FM);letter-spacing:2px}
@keyframes fUp{0%{opacity:1;transform:translateY(0) scale(1)}15%{transform:translateY(-14px) scale(1.15)}100%{opacity:0;transform:translateY(-80px) scale(.7)}}
@keyframes fUpCrit{0%{opacity:1;transform:translateY(0) scale(1) rotate(-3deg)}10%{transform:translateY(-10px) scale(1.35) rotate(2deg)}25%{transform:translateY(-22px) scale(1.45) rotate(-1deg)}100%{opacity:0;transform:translateY(-100px) scale(.6) rotate(0)}}

/* ── ACTION BAR ── */
.azone{height:321px;width:100%;max-width:1060px;display:flex;flex-direction:column;gap:12px;margin:0 auto}
.abar{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
.abtn{position:relative;overflow:hidden;background:rgba(3,10,18,.92);border:1px solid rgba(0,240,255,.18);color:var(--t2);font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;padding:16px 8px 13px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:7px;transition:all .22s;backdrop-filter:blur(10px);clip-path:polygon(8px 0%,100% 0%,100% calc(100% - 8px),calc(100% - 8px) 100%,0% 100%,0% 8px)}
.abtn::before{content:'';position:absolute;top:-100%;left:-60%;width:60%;height:300%;background:linear-gradient(90deg,transparent,rgba(0,240,255,.12),transparent);transform:rotate(20deg);transition:left .5s}
.abtn:hover:not(:disabled)::before{left:160%}
.abtn:hover:not(:disabled){border-color:var(--c);color:var(--c);box-shadow:0 0 24px rgba(0,240,255,.18),inset 0 0 20px rgba(0,240,255,.04);transform:translateY(-3px)}
.abtn:active:not(:disabled){transform:translateY(1px)}
.abtn .ico{font-size:25.3px;line-height:1;filter:drop-shadow(0 0 4px currentColor)}
.abtn .lbl{font-size:9.77px;letter-spacing:3px}
.abtn .cost{position:absolute;top:7px;right:9px;font-family:var(--FM);font-size:9.2px;color:var(--orange);letter-spacing:1px}
.abtn:disabled{opacity:.3;cursor:not-allowed;border-color:rgba(255,255,255,.06);transform:none!important;box-shadow:none!important}
.abtn.special{border-color:rgba(255,200,32,.25);background:rgba(8,6,1,.92)}
.abtn.special:hover:not(:disabled){border-color:var(--gold);color:var(--gold);box-shadow:0 0 28px rgba(255,200,32,.2)}
.abtn.special.sr{border-color:var(--gold);color:var(--gold);animation:srP 1.1s ease-in-out infinite}
@keyframes srP{0%,100%{box-shadow:0 0 12px rgba(255,200,32,.22);border-color:rgba(255,200,32,.6)}50%{box-shadow:0 0 32px rgba(255,200,32,.45);border-color:var(--gold)}}

/* ── COMBAT LOG ── */
.clog{height:204px;width:100%;background:rgba(3,9,16,.82);border:1px solid rgba(0,240,255,.1);overflow:hidden;backdrop-filter:blur(10px);margin-bottom:30px;clip-path:polygon(6px 0%,100% 0%,100% calc(100% - 6px),calc(100% - 6px) 100%,0 100%,0 6px);font-weight:900;display:flex;flex-direction:column}
.clhdr{display:flex;align-items:center;justify-content:space-between;padding:7px 14px;border-bottom:1px solid rgba(0,240,255,.07);background:rgba(0,240,255,.025)}
.cltit{font-family:var(--FD);font-size:10.35px;letter-spacing:3px;color:var(--c);display:flex;align-items:center;gap:8px}
.cldot{width:7px;height:7px;border-radius:50%;background:var(--c);box-shadow:0 0 8px var(--c);animation:ldot 1.5s ease-in-out infinite}
@keyframes ldot{0%,100%{opacity:.45}50%{opacity:1}}
.clcl{background:none;border:none;font-family:var(--FD);font-size:9.2px;letter-spacing:2px;color:var(--t3);cursor:pointer;transition:color .2s}
.clcl:hover{color:var(--c)}
.clbody{flex:1;min-height:0;overflow-y:auto;padding:8px 14px;display:flex;flex-direction:column;gap:3px}
.clbody::-webkit-scrollbar{width:2px}
.clbody::-webkit-scrollbar-thumb{background:rgba(0,240,255,.25)}
.le{font-family:var(--FB);font-size:13.8px;font-weight:600;letter-spacing:.3px;line-height:1.5;color:var(--t2);padding:2px 0;border-bottom:1px solid rgba(0,240,255,.035);animation:leIn .22s ease}
@keyframes leIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
.le:last-child{border-bottom:none}
.le .trn{font-family:var(--FM);font-size:9.2px;color:var(--t3);margin-right:7px}
.le.pa,.le.ea{font-size:clamp(15px,1.05rem,17.25px);font-weight:600;line-height:1.45;letter-spacing:.25px}
.le.pa{color:rgba(175,248,255,.94);text-shadow:0 0 14px rgba(0,240,255,.2)}
.le.ea{color:rgba(238,210,255,.94);text-shadow:0 0 14px rgba(212,0,255,.22)}
.le.pa .trn{font-size:10.35px;color:rgba(0,240,255,.52)}
.le.ea .trn{font-size:10.35px;color:rgba(212,0,255,.52)}
.le.pa .actor{color:var(--c);text-shadow:0 0 10px rgba(0,240,255,.4)}
.le.ea .actor{color:var(--m);text-shadow:0 0 10px rgba(212,0,255,.38)}
.le.pa .dt,.le.ea .dt{color:var(--red);font-weight:700;text-shadow:0 0 6px rgba(255,34,68,.35)}
.le.pa .ht,.le.ea .ht{color:var(--green);font-weight:700}
.le.pa .ct,.le.ea .ct{color:var(--gold);font-family:var(--FD);font-size:clamp(11px,.95em,12.65px)}
.le.sys{color:var(--t3);font-style:italic}
.dt{color:var(--red);font-weight:700}
.ht{color:var(--green);font-weight:700}
.ct{color:var(--gold);font-family:var(--FD);font-size:11.24px}

/* ── MODAL ── */
.mbdrop{position:fixed;inset:0;z-index:200;background:rgba(1,4,8,.94);backdrop-filter:blur(16px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s}
.mbdrop.open{opacity:1;pointer-events:all}
.mwin{position:relative;width:min(93vw,700px);background:linear-gradient(180deg,var(--surface) 0%,var(--surface2) 100%);border:1px solid rgba(0,240,255,.22);box-shadow:0 0 100px rgba(0,0,0,.85),0 0 60px rgba(0,240,255,.04);overflow:hidden;transform:scale(.94) translateY(18px);transition:transform .28s cubic-bezier(.2,.8,.2,1);clip-path:polygon(14px 0%,100% 0%,100% calc(100% - 14px),calc(100% - 14px) 100%,0 100%,0 14px)}
.mbdrop.open .mwin{transform:scale(1) translateY(0)}
.mwin::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--c),var(--m),transparent);z-index:2}
.mhdr{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-bottom:1px solid rgba(0,240,255,.09);background:rgba(0,240,255,.02)}
.mhttl{font-family:var(--FD);font-size:11.5px;font-weight:700;letter-spacing:3px;color:var(--c)}
.mcl{background:none;border:1px solid rgba(0,240,255,.22);color:var(--t2);width:28px;height:28px;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:monospace}
.mcl:hover{border-color:var(--c);color:var(--c);box-shadow:0 0 10px rgba(0,240,255,.2)}
.mbody{display:grid;grid-template-columns:170px 1fr;min-height:340px}
.mport{position:relative;background:radial-gradient(ellipse 80% 70% at 50% 40%,rgba(0,70,120,.28) 0%,transparent 70%),var(--surface);display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:20px;border-right:1px solid rgba(0,240,255,.07);overflow:hidden}
.mport::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(180deg,transparent,transparent 3px,rgba(0,0,0,.07) 3px,rgba(0,0,0,.07) 4px);pointer-events:none}
.mfig{animation:avFloat 4.2s ease-in-out infinite;position:relative;z-index:2}
.minfo{padding:22px;display:flex;flex-direction:column;gap:16px;overflow-y:auto;max-height:340px}
.minfo::-webkit-scrollbar{width:2px}
.minfo::-webkit-scrollbar-thumb{background:rgba(0,240,255,.2)}
.mname{font-family:var(--FD);font-size:27.6px;font-weight:900;letter-spacing:2px;line-height:1;color:var(--t1)}
.mrar{font-family:var(--FD);font-size:10.35px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--rc,var(--c));text-shadow:0 0 10px var(--rc,var(--c))}
.mdiv{height:1px;background:rgba(0,240,255,.09)}
.msec{font-family:var(--FD);font-size:9.2px;letter-spacing:3px;text-transform:uppercase;color:var(--t3);margin-bottom:8px}
.msts{display:flex;flex-direction:column;gap:9px}
.mst{display:flex;flex-direction:column;gap:3px}
.mstr{display:flex;justify-content:space-between}
.mstl{font-family:var(--FD);font-size:10.35px;letter-spacing:2px;color:var(--t2)}
.mstv{font-family:var(--FM);font-size:16.1px;font-weight:700;color:var(--t1)}
.mstbar{height:3px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden}
.mstfil{height:100%;border-radius:2px;background:var(--sc);box-shadow:0 0 6px var(--sc)}
.mlore{font-family:var(--FB);font-size:13.8px;font-weight:400;line-height:1.75;color:var(--t2)}

/* ── LOBBY ── */
#arena-lobby{position:relative;z-index:10;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:calc(100vh - 56px);padding:24px}
#arena-lobby.hidden{display:none}
#arena-play{display:none;width:100%}
#arena-play.visible{display:flex}
.lobby-box{width:min(92vw,520px);background:linear-gradient(180deg,var(--surface) 0%,var(--surface2) 100%);border:1px solid rgba(0,240,255,.22);padding:36px 40px;clip-path:polygon(0 12px,12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%);text-align:center}
.lobby-box.lobby-box--3v3{width:min(96vw,960px);padding:28px 24px}
.lobby-title{font-family:var(--FD);font-size:16.1px;font-weight:900;letter-spacing:4px;color:var(--c);margin-bottom:8px}
.lobby-sub{font-family:var(--FM);font-size:11.5px;letter-spacing:2px;color:var(--t3);margin-bottom:24px}
.lobby-avatar-wrap{margin-bottom:20px}
.lobby-avatar-select{width:100%;padding:10px 14px;background:rgba(0,0,0,.4);border:1px solid rgba(0,240,255,.2);color:var(--t1);font-family:var(--FM);font-size:13.8px;cursor:pointer}
.lobby-play-btn{display:block;width:100%;padding:14px 24px;background:transparent;border:1px solid var(--c);color:var(--c);font-family:var(--FD);font-size:12.65px;font-weight:700;letter-spacing:3px;cursor:pointer;transition:all .22s;margin-bottom:16px}
.lobby-play-btn:hover{background:var(--cg);box-shadow:0 0 24px rgba(0,240,255,.2)}
.lobby-mode-row{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.lobby-mode-btn{flex:1;min-width:180px;padding:20px 24px;background:rgba(0,0,0,.5);border:1px solid rgba(0,240,255,.25);color:var(--t2);font-family:var(--FD);font-size:13.8px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .22s;clip-path:polygon(8px 0%,100% 0%,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.lobby-mode-btn:hover:not(:disabled){border-color:var(--c);color:var(--c);box-shadow:0 0 20px rgba(0,240,255,.15)}
.lobby-mode-btn.disabled{opacity:.5;cursor:not-allowed}
.lobby-mode-btn .mode-label{display:block;font-size:16.1px;margin-bottom:4px}
.lobby-mode-btn .mode-hint{font-family:var(--FM);font-size:10.35px;letter-spacing:1px;color:var(--t3)}
.lobby-pve-submodes{display:none;flex-direction:column;gap:10px;margin-top:14px}
.lobby-pve-submodes.show{display:flex}
.lobby-submode-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.lobby-submode-btn{padding:12px 14px;background:rgba(0,0,0,.5);border:1px solid rgba(0,240,255,.22);color:var(--t2);font-family:var(--FD);font-size:11.5px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .2s;clip-path:polygon(8px 0%,100% 0%,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%,0 8px)}
.lobby-submode-btn:hover{border-color:var(--c);color:var(--c);box-shadow:0 0 18px rgba(0,240,255,.15)}
.lobby-submode-cancel{padding:9px 14px;background:transparent;border:1px solid rgba(255,255,255,.14);color:var(--t2);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .2s}
.lobby-submode-cancel:hover{border-color:var(--t2);color:var(--t1)}
.lobby-section{display:none}
.lobby-section.active{display:block}
.lobby-back-btn{margin-top:10px;padding:9px 14px;background:transparent;border:1px solid rgba(255,255,255,.14);color:var(--t2);font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .2s}
.lobby-back-btn:hover{border-color:var(--t2);color:var(--t1)}
.lobby-setup-wrap{display:flex;flex-direction:column;gap:14px}
.lobby-setup-slots{display:grid;gap:10px;grid-template-columns:1fr}
.lobby-setup-slots.m3{grid-template-columns:repeat(3,1fr)}
.lobby-avatar-slot{background:transparent;border:none;padding:0;cursor:pointer;text-align:left}
.lobby-avatar-slot .avatar-card{width:100%}
.lobby-setup-actions{display:flex;gap:10px;justify-content:center;align-items:center}
.lobby-play-btn:disabled{opacity:.35;cursor:not-allowed;box-shadow:none!important;background:transparent!important}

.lobby-avatar-selector{position:fixed;inset:0;z-index:560;background:rgba(1,4,8,.88);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.lobby-avatar-selector.show{opacity:1;pointer-events:all}
.lavs-box{width:min(92vw,860px);max-height:min(88vh,720px);background:linear-gradient(180deg,var(--surface) 0%,var(--surface2) 100%);border:1px solid rgba(0,240,255,.2);clip-path:polygon(0 12px,12px 0,100% 0,100% calc(100% - 12px),calc(100% - 12px) 100%,0 100%);display:flex;flex-direction:column;overflow:hidden}
.lavs-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid rgba(0,240,255,.08)}
.lavs-title{font-family:var(--FD);font-size:11.5px;letter-spacing:3px;color:var(--c)}
.lavs-close{background:none;border:1px solid rgba(0,240,255,.2);color:var(--t2);width:28px;height:28px;cursor:pointer;transition:all .2s}
.lavs-close:hover{border-color:var(--c);color:var(--c)}
.lavs-grid{padding:14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;overflow:auto}
.lavs-grid::-webkit-scrollbar{width:2px}.lavs-grid::-webkit-scrollbar-thumb{background:rgba(0,240,255,.2)}
.lavs-knd-card{cursor:pointer}
.lavs-empty{padding:26px;text-align:center;font-family:var(--FM);font-size:12.65px;color:var(--t3)}

/* Reused KND card system from /tools/cards */
.avatar-card{position:relative;background:#060e14;border:1px solid rgba(0,229,255,.12);border-radius:4px;overflow:hidden;transition:transform .3s cubic-bezier(.2,.8,.2,1),border-color .3s,box-shadow .3s}
.avatar-card:hover{transform:translateY(-4px);border-color:rgba(0,229,255,.45);box-shadow:0 20px 60px rgba(0,0,0,.6),0 0 0 1px rgba(0,229,255,.1),0 0 40px rgba(0,229,255,.08)}
.avatar-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--rarity-color,#00e5ff);opacity:.85;z-index:2}
.avatar-card::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 0%,rgba(0,229,255,.03) 50%,transparent 100%);background-size:100% 8px;opacity:0;transition:opacity .3s;pointer-events:none;z-index:3;animation:scanSweep 2s linear infinite}
.avatar-card:hover::after{opacity:1}
@keyframes scanSweep{0%{background-position:0 -100%}100%{background-position:0 200%}}

.card-thumb{position:relative;height:170px;background:radial-gradient(ellipse at center,rgba(0,229,255,.05) 0%,transparent 70%);display:flex;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid rgba(0,229,255,.08)}
.thumb-model{width:100px;height:136px;position:relative;display:flex;align-items:center;justify-content:center;animation:thumbFloat 4s ease-in-out infinite}
@keyframes thumbFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.thumb-model img{width:100%;height:100%;object-fit:contain;animation:glowPulse 2.5s ease-in-out infinite}
@keyframes glowPulse{0%,100%{filter:drop-shadow(0 0 10px var(--rarity-glow,rgba(0,229,255,.4)))}50%{filter:drop-shadow(0 0 22px var(--rarity-glow,rgba(0,229,255,.4)))}}
.thumb-silhouette{width:80px;height:120px;position:relative;display:flex;flex-direction:column;align-items:center;gap:0}
.thumb-sil-head{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(0,229,255,.2),rgba(0,229,255,.05));border:1px solid rgba(0,229,255,.3);box-shadow:0 0 20px rgba(0,229,255,.15),inset 0 0 15px rgba(0,229,255,.08)}
.thumb-sil-body{width:56px;height:72px;margin-top:4px;clip-path:polygon(20% 0%,80% 0%,100% 100%,0% 100%);background:linear-gradient(180deg,rgba(0,229,255,.15),rgba(0,229,255,.04));border:1px solid rgba(0,229,255,.2)}
.thumb-hex{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:82.8px;color:rgba(0,229,255,.12);pointer-events:none;line-height:1}
.thumb-ring{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);width:80px;height:10px;border-radius:50%;border:1px solid rgba(0,229,255,.4);box-shadow:0 0 12px rgba(0,229,255,.2);animation:ringPulse 2s ease-in-out infinite}
@keyframes ringPulse{0%,100%{opacity:.4;transform:translateX(-50%) scaleX(1)}50%{opacity:.8;transform:translateX(-50%) scaleX(1.08)}}
.card-rarity-badge{position:absolute;top:10px;right:10px;font-family:var(--FD);font-size:9.2px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:3px 8px;border-radius:2px;border:1px solid var(--rarity-color,#00e5ff);color:var(--rarity-color,#00e5ff);background:rgba(0,0,0,.6);z-index:4;text-shadow:0 0 8px var(--rarity-color,#00e5ff)}
.card-id{position:absolute;top:10px;left:10px;font-family:var(--FD);font-size:9.2px;letter-spacing:1px;color:var(--t3);z-index:4}

.card-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:10px}
.card-name{font-family:var(--FD);font-size:16.1px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--t1);line-height:1.15}
.card-tagline{font-size:11.5px;font-weight:400;letter-spacing:.5px;color:var(--t2);margin-top:-6px}
.card-stats{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.stat-item{display:flex;flex-direction:column;gap:3px}
.stat-header{display:flex;justify-content:space-between;align-items:center}
.stat-label{font-family:var(--FD);font-size:8.62px;letter-spacing:1.4px;text-transform:uppercase;color:var(--t3)}
.stat-value{font-family:var(--FD);font-size:11.5px;font-weight:600;color:var(--t1)}
.stat-bar{height:2px;background:rgba(0,229,255,.1);border-radius:1px;overflow:hidden}
.stat-fill{height:100%;background:var(--stat-color,#00e5ff);border-radius:1px;box-shadow:0 0 4px var(--stat-color,#00e5ff);transition:width .8s cubic-bezier(.2,.8,.2,1)}
.card-footer{display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid rgba(0,229,255,.08)}
.card-class{font-size:10.35px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--t3)}
.inspect-btn{background:transparent;border:1px solid rgba(0,229,255,.25);color:#00e5ff;font-family:var(--FD);font-size:9.2px;font-weight:600;letter-spacing:1.8px;text-transform:uppercase;padding:5px 11px;cursor:pointer;border-radius:2px;transition:all .2s;position:relative;overflow:hidden}
.inspect-btn::before{content:'';position:absolute;inset:0;background:rgba(0,229,255,.14);opacity:0;transition:opacity .2s}
.inspect-btn:hover::before{opacity:1}
.inspect-btn:hover{border-color:#00e5ff;box-shadow:0 0 14px rgba(0,229,255,.25);transform:scale(1.03)}

.avatar-card.is-placeholder{opacity:.75;filter:saturate(.75)}
.avatar-card.is-placeholder:hover{transform:none}
.avatar-card.is-placeholder .inspect-btn{pointer-events:none}

/* ── RESPONSIVE ── */
@media(max-width:800px){.bcard{width:230px}.vs-glyph{font-size:42.32px}.vs-col{padding:0 13.8px}.abar{grid-template-columns:repeat(2,1fr)}}
@media(max-width:644px){.bcard{width:201.25px}.vs-col{padding:0 6.9px}.sgrid{grid-template-columns:1fr}.mbody{grid-template-columns:1fr}.mport{height:184px}body{min-height:100vh;min-height:100dvh;padding-left:max(0px,env(safe-area-inset-left,0px));padding-right:max(0px,env(safe-area-inset-right,0px));padding-bottom:max(0px,env(safe-area-inset-bottom,0px))}.lavs-grid{grid-template-columns:repeat(auto-fill,minmax(min(160px,100%),1fr))}}
/* Full-page arena: ask for landscape on phones in portrait (embed iframes skip this) */
.mw-portrait-gate{display:none;position:fixed;inset:0;z-index:20000;flex-direction:column;align-items:center;justify-content:center;padding:max(24px,env(safe-area-inset-top,0)) max(20px,env(safe-area-inset-right,0)) max(24px,env(safe-area-inset-bottom,0)) max(20px,env(safe-area-inset-left,0));background:radial-gradient(ellipse 80% 60% at 50% 20%,rgba(0,240,255,.12) 0%,transparent 55%),radial-gradient(ellipse 70% 50% at 80% 80%,rgba(212,0,255,.08) 0%,transparent 50%),rgba(1,5,10,.97);backdrop-filter:blur(12px);text-align:center;box-sizing:border-box}
.mw-portrait-gate-inner{max-width:340px}
.mw-portrait-gate-phone{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px;font-size:42px;line-height:1;filter:drop-shadow(0 0 20px rgba(0,240,255,.35))}
.mw-portrait-gate-arrow{display:inline-block;animation:mwArenaPortraitSpin 2.2s ease-in-out infinite;font-size:36px;color:var(--c)}
@keyframes mwArenaPortraitSpin{0%,100%{transform:rotate(0)}35%,65%{transform:rotate(-90deg)}}
.mw-portrait-gate-title{font-family:var(--FD);font-size:15px;font-weight:800;letter-spacing:4px;text-transform:uppercase;color:var(--t1);margin-bottom:10px;text-shadow:0 0 24px rgba(0,240,255,.25)}
.mw-portrait-gate-sub{font-family:var(--FB);font-size:14px;line-height:1.5;color:var(--t2);font-weight:500}
.mw-portrait-gate-skip{margin-top:22px;padding:10px 18px;font-family:var(--FB);font-size:13px;font-weight:600;color:var(--t2);background:rgba(255,255,255,.06);border:1px solid rgba(0,240,255,.22);border-radius:4px;cursor:pointer}
.mw-portrait-gate-skip:hover{color:var(--t1);border-color:var(--cb);background:rgba(0,240,255,.08)}
html.mw-portrait-dismissed body:not(.arena-embed) .mw-portrait-gate{display:none!important}
@media (orientation:portrait) and (max-width:926px){body:not(.arena-embed) .mw-portrait-gate{display:flex}}
@media (orientation:landscape) and (max-height:420px){
  .arena{padding:10px 12px;gap:10px;min-height:calc(100dvh - 48px)}
  .azone{height:auto;min-height:200px;max-height:42vh}
  .stage{flex-wrap:wrap;justify-content:center;gap:8px}
}
</style>
</head>
<body<?php echo $embed ? ' class="arena-embed mind-wars-arena-context"' : ''; ?>>
<?php if (!$embed): ?>
<div id="mw-portrait-gate" class="mw-portrait-gate" role="dialog" aria-modal="true" aria-labelledby="mw-arena-portrait-title">
  <div class="mw-portrait-gate-inner">
    <div class="mw-portrait-gate-phone" aria-hidden="true"><span>📱</span><span class="mw-portrait-gate-arrow">↻</span></div>
    <p id="mw-arena-portrait-title" class="mw-portrait-gate-title">Rotate your device</p>
    <p class="mw-portrait-gate-sub">The battle arena is laid out for landscape. Turn your device sideways for the full view.</p>
    <button type="button" class="mw-portrait-gate-skip" onclick="document.documentElement.classList.add('mw-portrait-dismissed')">Continue in portrait</button>
  </div>
</div>
<?php endif; ?>
<?php if ($embed): ?><div class="arena-embed-inner"><?php endif; ?>
<div id="bg"></div>
<div id="floor"></div>
<div id="sf"></div>

<!-- NAV -->
<?php if (!$embed): ?>
<nav class="nav">
  <div class="nav-logo">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <polygon points="12,2 22,7 22,17 12,22 2,17 2,7"/>
    </svg>
    KND
  </div>
  <div class="nav-center">MIND WARS — <em>BATTLE ARENA</em></div>
  <div class="nav-right">SEASON <b>01</b> &nbsp;·&nbsp; RANK <b>GOLD IV</b> &nbsp;·&nbsp; WIN <b id="wc">0</b></div>
</nav>
<?php endif; ?>

<div id="arena-lobby">
  <div class="lobby-box">
    <div id="arena-initial-play-wrap" class="lobby-section active">
      <button type="button" class="lobby-play-btn" id="arena-play-now-btn">PLAY</button>
    </div>

    <div id="arena-mode-content" class="lobby-section">
      <div class="lobby-title">BATTLE ARENA</div>
      <div class="lobby-sub">Choose your battle type</div>
      <div class="lobby-mode-row" id="lobby-mode-row">
        <button type="button" class="lobby-mode-btn" id="lobby-mode-pve" data-mode="pve"><span class="mode-label">PVE</span><span class="mode-hint">Battle AI</span></button>
        <button type="button" class="lobby-mode-btn disabled" id="lobby-mode-pvp" disabled><span class="mode-label">PVP</span><span class="mode-hint">Coming soon</span></button>
      </div>
    </div>

    <div id="arena-pve-submode-content" class="lobby-section">
      <div class="lobby-title">PVE MODE</div>
      <div class="lobby-sub">Select combat format</div>
      <div id="lobby-pve-submodes" class="lobby-pve-submodes show" aria-live="polite">
        <div class="lobby-submode-row">
          <button type="button" class="lobby-submode-btn" id="lobby-pve-1v1">PVE 1v1</button>
          <button type="button" class="lobby-submode-btn" id="lobby-pve-3v3">PVE 3v3</button>
        </div>
        <button type="button" class="lobby-submode-cancel" id="lobby-pve-submode-cancel">BACK</button>
      </div>
    </div>

    <div id="arena-avatar-setup-content" class="lobby-section">
      <div class="lobby-title" id="lobby-setup-title">PVE 1V1</div>
      <div class="lobby-sub" id="lobby-setup-sub">Choose your avatar</div>
      <div class="lobby-setup-wrap">
        <div id="lobby-setup-slots" class="lobby-setup-slots"></div>
        <div class="lobby-setup-actions">
          <button type="button" class="lobby-back-btn" id="lobby-setup-back-btn">BACK</button>
          <button type="button" class="lobby-play-btn" id="lobby-setup-play-btn" disabled>PLAY</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="lobby-avatar-selector" class="lobby-avatar-selector" aria-hidden="true">
  <div class="lavs-box">
    <div class="lavs-head">
      <div class="lavs-title">SELECT AVATAR</div>
      <button type="button" class="lavs-close" id="lobby-avatar-selector-close">✕</button>
    </div>
    <div class="lavs-grid" id="lobby-avatar-selector-grid"></div>
  </div>
</div>

<div id="arena-play" class="arena">
  <div class="bhdr">
    <div class="rpill" id="rb">ROUND 1</div>
    <div class="tpill pt" id="tp">YOUR TURN</div>
  </div>

  <!-- STAGE -->
  <div class="stage">

    <!-- PLAYER -->
    <div class="bcard aturn" id="pc" style="--rc:var(--gold)">
      <div class="redflash"></div>
      <div class="cholo">
        <div class="vid">#001</div>
        <div class="vrar" style="--rc:var(--gold)">LEGENDARY</div>
        <div class="vlvl">Lv 1</div>
        <div class="hbeam"></div>
        <div class="avfig" id="pfig">
          <div class="avsil">
            <div class="sh"></div><div class="sn"></div><div class="st"></div>
            <div class="sl"><div class="slg"></div><div class="slg"></div></div>
          </div>
        </div>
        <div class="hplat"><div class="hdot"></div><div class="hr1"></div><div class="hr2"></div></div>
      </div>
      <div class="cbody">
        <div class="cnrow"><span class="cname">ZEUS</span><span class="cclass">DEITY</span></div>
        <div class="hpw">
          <div class="hpm"><span class="hpl">HEALTH</span><span class="hpn" id="phv">920 / 920</span></div>
          <div class="hpt"><div class="hpf" id="phb" style="width:100%"></div></div>
        </div>
        <div class="enw">
          <div class="enm"><span class="enl">ENERGY</span><span class="env" id="pev">3 / 5</span></div>
          <div class="enorbs" id="po"></div>
        </div>
        <div class="sgrid">
          <div class="si"><div class="sir"><span class="sik">MND</span><span class="siv">92</span></div><div class="sitr"><div class="sif" style="--sc:#c040ff;width:92%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">FCS</span><span class="siv">78</span></div><div class="sitr"><div class="sif" style="--sc:var(--c);width:78%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">SPD</span><span class="siv">65</span></div><div class="sitr"><div class="sif" style="--sc:var(--green);width:65%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">LCK</span><span class="siv">88</span></div><div class="sitr"><div class="sif" style="--sc:var(--gold);width:88%"></div></div></div>
        </div>
        <div class="sbrow" id="ps"></div>
      </div>
      <div class="cfooter"><span class="cxp">XP 4,820</span><button class="abtn2" onclick="openA('player')">⬡ ANALYZE</button></div>
    </div>

    <!-- VS -->
    <div class="vs-col">
      <div class="vs-sep"></div>
      <div class="vs-glyph">VS</div>
      <div class="vs-sep"></div>
    </div>

    <!-- ENEMY -->
    <div class="bcard enemy" id="ec" style="--rc:var(--m)">
      <div class="redflash"></div>
      <div class="itag" id="ei">⚔ PREPARING STRIKE</div>
      <div class="cholo">
        <div class="vid">#047</div>
        <div class="vrar" style="--rc:var(--m)">EPIC</div>
        <div class="vlvl">Lv 1</div>
        <div class="hbeam"></div>
        <div class="avfig" id="efig">
          <div class="avsil">
            <div class="sh"></div><div class="sn"></div><div class="st"></div>
            <div class="sl"><div class="slg"></div><div class="slg"></div></div>
          </div>
        </div>
        <div class="hplat"><div class="hdot"></div><div class="hr1"></div><div class="hr2"></div></div>
      </div>
      <div class="cbody">
        <div class="cnrow"><span class="cname">ARIA</span><span class="cclass">ARCHITECT</span></div>
        <div class="hpw">
          <div class="hpm"><span class="hpl">HEALTH</span><span class="hpn" id="ehv">750 / 750</span></div>
          <div class="hpt"><div class="hpf" id="ehb" style="width:100%"></div></div>
        </div>
        <div class="enw">
          <div class="enm"><span class="enl">ENERGY</span><span class="env" id="eev">2 / 4</span></div>
          <div class="enorbs" id="eo"></div>
        </div>
        <div class="sgrid">
          <div class="si"><div class="sir"><span class="sik">MND</span><span class="siv">98</span></div><div class="sitr"><div class="sif" style="--sc:#c040ff;width:98%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">FCS</span><span class="siv">91</span></div><div class="sitr"><div class="sif" style="--sc:var(--c);width:91%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">SPD</span><span class="siv">44</span></div><div class="sitr"><div class="sif" style="--sc:var(--green);width:44%"></div></div></div>
          <div class="si"><div class="sir"><span class="sik">LCK</span><span class="siv">62</span></div><div class="sitr"><div class="sif" style="--sc:var(--gold);width:62%"></div></div></div>
        </div>
        <div class="sbrow" id="es"><span class="sbadge shield">SHIELD ×1</span></div>
      </div>
      <div class="cfooter"><span class="cxp">XP 6,140</span><button class="abtn2" onclick="openA('enemy')">⬡ ANALYZE</button></div>
    </div>

  </div><!-- end stage -->

  <div class="arena-surrender-row">
    <button type="button" id="arena-sfx-toggle" class="arena-sfx-toggle" aria-label="Toggle sound">🔊 SFX</button>
    <button type="button" id="surrender-btn" class="surrender-btn-arena" onclick="showSurrenderConfirm()">SURRENDER</button>
  </div>

  <!-- ACTIONS -->
  <div class="azone">
    <div class="abar">
      <button class="abtn" id="ba" onclick="act('attack')"><span class="cost">1⚡</span><span class="ico">⚔️</span><span class="lbl">ATTACK</span></button>
      <button class="abtn" id="bd" onclick="act('defend')"><span class="cost">0⚡</span><span class="ico">🛡️</span><span class="lbl">DEFEND</span></button>
      <button class="abtn" id="bb" onclick="act('ability')"><span class="cost">2⚡</span><span class="ico">🧠</span><span class="lbl">ABILITY</span></button>
      <button class="abtn special" id="bs" onclick="act('special')"><span class="cost">5⚡</span><span class="ico">🌌</span><span class="lbl">SPECIAL</span></button>
      <button class="abtn" id="bh" onclick="act('heal')"><span class="cost">2⚡</span><span class="ico">💚</span><span class="lbl">HEAL</span></button>
    </div>
    <div class="clog">
      <div class="clhdr"><div class="cltit"><div class="cldot"></div> COMBAT LOG</div><button class="clcl" onclick="clearLog()">CLEAR</button></div>
      <div class="clbody" id="lb"><div class="le sys">— Battle initiated. Round 1 begins. May the sharpest mind prevail. —</div></div>
    </div>
  </div>

</div><!-- end arena -->

<!-- MODAL -->
<div class="mbdrop" id="modal">
  <div class="mwin">
    <div class="mhdr"><span class="mhttl">⬡ HOLOGRAPHIC ANALYSIS</span><button class="mcl" onclick="closeA()">✕</button></div>
    <div class="mbody">
      <div class="mport" id="mp">
        <div class="mfig" id="mf"></div>
        <div class="hplat" style="bottom:14px;width:80px;position:absolute"><div class="hdot"></div><div class="hr1"></div></div>
      </div>
      <div class="minfo">
        <div><div class="mname" id="mn">—</div><div class="mrar" id="mr">—</div></div>
        <div class="mdiv"></div>
        <div><div class="msec">COMBAT STATS</div><div class="msts" id="ms"></div></div>
        <div class="mdiv"></div>
        <div><div class="msec">LORE</div><p class="mlore" id="ml">—</p></div>
      </div>
    </div>
  </div>
</div>

<script>window.MW_ARENA_CSRF = '<?php echo htmlspecialchars($csrfToken); ?>';</script>
<?php
$mwAudioV = file_exists(__DIR__ . '/../../assets/js/mind-wars-audio.js') ? filemtime(__DIR__ . '/../../assets/js/mind-wars-audio.js') : 0;
?>
<script src="/assets/js/mind-wars-audio.js?v=<?php echo $mwAudioV; ?>"></script>
<script src="mind-wars-arena.js?v=<?php echo file_exists(__DIR__ . '/mind-wars-arena.js') ? filemtime(__DIR__ . '/mind-wars-arena.js') : time(); ?>"></script>
<div id="endgame">
  <div id="endgame-bg"></div>
  <div id="endgame-confetti" aria-hidden="true"></div>
  <div id="endgame-panel">
    <div class="eg-tl" id="egtl"></div>
    <div class="eg-box">
      <div class="eg-res" id="eg-result"></div>
      <div class="eg-sub" id="eg-sub"></div>
      <div class="eg-div" id="egd1"></div>
      <div class="eg-sr" id="eg-stats"></div>
      <div class="eg-div" id="egd2"></div>
      <div class="eg-xp-wrap" id="egxp">
        <div class="eg-xp-label"><span>EXPERIENCE</span><span id="egxpv">+0 XP</span></div>
        <div class="eg-xp-track"><div class="eg-xp-fill" id="egxpf"></div></div>
      </div>
      <div class="eg-rw" id="eg-rw"></div>
      <p class="eg-q" id="eg-flavor"></p>
      <div class="eg-btns" id="eg-btns">
        <button type="button" class="eg-btn" onclick="restartBattle()">REMATCH</button>
        <button type="button" class="eg-btn sec" onclick="closeEndgame()">RETURN</button>
      </div>
    </div>
    <div class="eg-bl" id="egbl"></div>
  </div>
</div>
<div id="surrender-confirm">
  <div class="sc-box">
    <div class="sc-ttl">SURRENDER</div>
    <p class="sc-txt">Are you sure you want to abandon the battle?<br>This counts as a defeat.</p>
    <div class="sc-btns">
      <button class="sc-yes" onclick="confirmSurrender()">YES, SURRENDER</button>
      <button class="sc-no" onclick="closeSurrenderConfirm()">CANCEL</button>
    </div>
  </div>
</div>
<?php if ($embed): ?></div><?php endif; ?>
</body>
</html>
