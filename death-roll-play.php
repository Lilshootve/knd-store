<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

// Obtener order_id desde GET o POST
$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$userId = !empty($_SESSION['dr_user_id']) ? (int)$_SESSION['dr_user_id'] : 0;

if (!$userId) {
    header('Location: /auth.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

echo generateHeader('Death Roll - Play', 'Play Death Roll and claim your digital reward');
?>

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section py-4 deathroll-hero">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient">Death Roll</span><br>
                    <span class="hero-subtitle-mini">Controlled-risk mini-game</span>
                </h1>
            </div>
        </div>
    </div>
</section>

<!-- Main Game Section -->
<section class="py-5">
    <div class="container-fluid">
        <div class="row">
            <!-- Unity Canvas Container -->
            <div class="col-lg-9 col-md-8">
                <div class="unity-container">
                    <div id="unity-container" class="unity-wrapper">
                        <div id="unity-loading" class="unity-loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-white">Loading Death Roll...</p>
                        </div>
                        <canvas id="unity-canvas" class="unity-canvas" style="display: none;"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Control Panel -->
            <div class="col-lg-3 col-md-4">
                <div class="deathroll-panel">
                    <div class="panel-header">
                        <h4><i class="fas fa-dice-d20 me-2"></i> Control Panel</h4>
                    </div>
                    <div class="panel-body">
                        <!-- Order ID Status -->
                        <div class="mb-3">
                            <label class="form-label small text-white-50">Order ID</label>
                            <input type="text" 
                                   id="order-id-input" 
                                   class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'); ?>" 
                                   placeholder="Enter Order ID"
                                   <?php echo $orderId ? 'readonly' : ''; ?>>
                            <small class="text-white-50">Order ID for Death Roll</small>
                        </div>
                        
                        <!-- Roll Button -->
                        <button id="roll-now-btn" 
                                class="btn btn-primary w-100 mb-3" 
                                <?php echo !$orderId ? 'disabled' : ''; ?>>
                            <i class="fas fa-dice me-2"></i> Roll Now
                        </button>
                        
                        <!-- Result Display -->
                        <div id="result-container" class="result-container" style="display: none;">
                            <div class="result-header">
                                <h5><i class="fas fa-trophy me-2"></i> Result</h5>
                            </div>
                            <div class="result-body">
                                <div class="mb-2">
                                    <span class="text-white-50 small">Number:</span>
                                    <span id="result-number" class="fw-bold text-white"></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-white-50 small">Rarity:</span>
                                    <span id="result-rarity" class="rarity-badge"></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-white-50 small">Reward:</span>
                                    <div id="result-reward" class="text-white fw-bold"></div>
                                </div>
                                <div class="mt-3">
                                    <h6 class="text-white-50 small mb-2">Instructions:</h6>
                                    <p id="result-instructions" class="text-white small"></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loading State -->
                        <div id="roll-loading" class="text-center" style="display: none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Processing...</span>
                            </div>
                            <p class="mt-2 text-white small">Executing Death Roll...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Unity WebGL Loader
(function() {
    const container = document.querySelector("#unity-container");
    const canvas = document.querySelector("#unity-canvas");
    const loadingBar = document.querySelector("#unity-loading");
    
    // Unity build path
    const buildUrl = "/unity/deathroll/Build";
    const loaderUrl = buildUrl + "/deathroll.loader.js";
    const config = {
        dataUrl: buildUrl + "/deathroll.data",
        frameworkUrl: buildUrl + "/deathroll.framework.js",
        codeUrl: buildUrl + "/deathroll.wasm",
        streamingAssetsUrl: "StreamingAssets",
        companyName: "KND Store",
        productName: "Death Roll",
        productVersion: "1.0",
    };
    
    let unityInstance = null;
    
    // Cargar Unity
    function loadUnity() {
        if (window.createUnityInstance) {
            window.createUnityInstance(canvas, config, (progress) => {
                // Progress callback
            }).then((instance) => {
                unityInstance = instance;
                loadingBar.style.display = "none";
                canvas.style.display = "block";
            }).catch((message) => {
                console.error("Error loading Unity:", message);
                loadingBar.innerHTML = '<p class="text-white">Error loading the game. The result will show in the panel.</p>';
            });
        } else {
            // Cargar loader script
            const script = document.createElement("script");
            script.src = loaderUrl;
            script.onload = () => {
                loadUnity();
            };
            script.onerror = () => {
                console.warn("Unity loader not available. Continuing without Unity.");
                loadingBar.innerHTML = '<p class="text-white">Unity unavailable. You can use the control panel.</p>';
            };
            document.body.appendChild(script);
        }
    }
    
    // Intentar cargar Unity al iniciar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadUnity);
    } else {
        loadUnity();
    }
    
    // Exponer función para enviar resultado a Unity
    window.sendResultToUnity = function(resultNumber, rarity, rewardName) {
        if (unityInstance && typeof unityInstance.SendMessage === 'function') {
            try {
                unityInstance.SendMessage('GameManager', 'OnRollResult', JSON.stringify({
                    number: resultNumber,
                    rarity: rarity,
                    reward: rewardName
                }));
            } catch (e) {
                console.warn("Error sending to Unity:", e);
            }
        }
    };
})();

// Roll Now Button Handler
document.addEventListener('DOMContentLoaded', function() {
    const rollBtn = document.getElementById('roll-now-btn');
    const orderIdInput = document.getElementById('order-id-input');
    const resultContainer = document.getElementById('result-container');
    const rollLoading = document.getElementById('roll-loading');
    
    rollBtn.addEventListener('click', async function() {
        const orderId = orderIdInput.value.trim();
        
        if (!orderId) {
            Swal.fire({
                icon: 'error',
                title: 'Order ID required',
                text: 'Please enter a valid Order ID.',
                confirmButtonColor: '#259cae'
            });
            return;
        }
        
        // Deshabilitar botón y mostrar loading
        rollBtn.disabled = true;
        rollLoading.style.display = 'block';
        resultContainer.style.display = 'none';
        
        try {
            const formData = new FormData();
            formData.append('order_id', orderId);
            
            const response = await fetch('/api/deathroll/roll.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            rollLoading.style.display = 'none';
            
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Error running the Death Roll');
            }
            
            // Mostrar resultado
            displayResult(data);
            
            // Enviar a Unity si está disponible
            if (window.sendResultToUnity) {
                window.sendResultToUnity(data.result_number, data.rarity, data.reward.name);
            }
            
            // Mostrar éxito
            Swal.fire({
                icon: 'success',
                title: 'Death Roll completed!',
                text: `Obtuviste: ${data.rarity} - ${data.reward.name}`,
                confirmButtonColor: '#259cae',
                timer: 3000
            });
            
        } catch (error) {
            rollLoading.style.display = 'none';
            rollBtn.disabled = false;
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error running Death Roll. Please try again.',
                confirmButtonColor: '#259cae'
            });
        }
    });
    
    function displayResult(data) {
        document.getElementById('result-number').textContent = data.result_number;
        
        const rarityEl = document.getElementById('result-rarity');
        rarityEl.textContent = data.rarity;
        rarityEl.className = 'rarity-badge tier-' + data.rarity.toLowerCase();
        
        document.getElementById('result-reward').textContent = data.reward.name;
        document.getElementById('result-instructions').textContent = data.claim_instructions || 'Contact Discord or WhatsApp to claim your reward.';
        
        resultContainer.style.display = 'block';
    }
});
</script>

<style>
/* Estilos adicionales para Death Roll Play Page */

.unity-container {
    background: rgba(26, 26, 46, 0.9);
    border-radius: 15px;
    padding: 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    min-height: 500px;
}

.unity-wrapper {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    background: #000;
    border-radius: 10px;
    overflow: hidden;
}

.unity-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: var(--knd-white);
}

.unity-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    display: block;
}

.deathroll-panel {
    background: rgba(26, 26, 46, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(37, 156, 174, 0.3);
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

.panel-header {
    background: linear-gradient(135deg, rgba(37, 156, 174, 0.2), rgba(138, 43, 226, 0.2));
    padding: 1rem;
    border-bottom: 1px solid rgba(37, 156, 174, 0.3);
}

.panel-header h4 {
    color: var(--knd-white);
    font-family: 'Orbitron', sans-serif;
    font-size: 1.2rem;
    margin: 0;
}

.panel-body {
    padding: 1.5rem;
}

.result-container {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 10px;
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid rgba(37, 156, 174, 0.2);
}

.result-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.result-header h5 {
    color: var(--knd-white);
    font-family: 'Orbitron', sans-serif;
    font-size: 1rem;
    margin: 0;
}

.rarity-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: 'Orbitron', sans-serif;
}

.rarity-badge.tier-common {
    background: rgba(200, 200, 200, 0.2);
    color: rgba(200, 200, 200, 0.9);
    border: 1px solid rgba(200, 200, 200, 0.5);
}

.rarity-badge.tier-rare {
    background: rgba(37, 156, 174, 0.2);
    color: var(--knd-neon-blue);
    border: 1px solid rgba(37, 156, 174, 0.5);
}

.rarity-badge.tier-epic {
    background: rgba(138, 43, 226, 0.2);
    color: var(--knd-electric-purple);
    border: 1px solid rgba(138, 43, 226, 0.5);
}

.rarity-badge.tier-legendary {
    background: rgba(255, 215, 0, 0.2);
    color: #ffd700;
    border: 1px solid rgba(255, 215, 0, 0.5);
}

@media (max-width: 768px) {
    .unity-wrapper {
        padding-bottom: 75%; /* Más alto en móvil */
    }
    
    .deathroll-panel {
        margin-top: 2rem;
    }
}
</style>

<?php 
echo generateFooter();
echo generateScripts();
?>

