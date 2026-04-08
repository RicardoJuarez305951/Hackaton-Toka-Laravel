<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rasca y Gana - Toka Games</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        .container { padding: 20px; padding-bottom: 100px; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .title-row { display: flex; align-items: center; gap: 15px; }
        .back-btn { 
            font-size: 24px; 
            color: #fff; 
            text-decoration: none;
            padding: 8px 15px;
            background: #3d3d5c;
            border-radius: 8px;
        }
        .title { font-size: 24px; font-weight: 800; }
        
        .balance {
            background: linear-gradient(135deg, #2d2d44 0%, #1a1a2e 100%);
            border-radius: 15px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #3d3d5c;
        }
        .balance-amount { font-size: 18px; font-weight: 700; color: #ffd700; }

        .game-area { margin-bottom: 20px; }

        .card-wrapper {
            background: linear-gradient(135deg, #2a2a4a 0%, #1e1e38 100%);
            border-radius: 16px;
            border: 3px solid #ffd700;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            padding: 15px;
            text-align: center;
        }
        .card-title { font-size: 18px; font-weight: 900; color: #1a1a2e; }
        .card-subtitle { font-size: 12px; color: rgba(26,26,46,0.7); }

        .canvas-wrapper {
            position: relative;
            width: 100%;
            height: 200px;
            background: #0a0a15;
            overflow: hidden;
        }

        .scratch-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .prize-display {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }
        .prize-value { font-size: 60px; font-weight: 900; color: #ffd700; }
        .prize-label { font-size: 18px; font-weight: 700; color: #ffd700; }

        .card-footer {
            padding: 15px;
            border-top: 2px solid #3d3d5c;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #3d3d5c;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #f59e0b 0%, #ffd700 100%);
            transition: width 0.1s;
        }

        .controls { margin-bottom: 20px; }

        .buy-btn {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .buy-btn:disabled { background: #3d3d5c; cursor: not-allowed; }
        .buy-btn:not(:disabled):hover { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); }

        .result-panel {
            display: none;
            margin-top: 15px;
            background: linear-gradient(135deg, #252542 0%, #1e1e38 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 2px solid #3d3d5c;
        }
        .result-panel.show { display: block; }
        .result-emoji { font-size: 30px; }
        .result-text { font-size: 20px; font-weight: 700; margin-left: 10px; }
        .result-text.win { color: #10b981; }
        .result-text.lose { color: #ef5350; }

        .loading {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .loading.hidden { display: none; }
        .loading-content {
            background: #1a1a2e;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="loading hidden" id="loading">
        <div class="loading-content">
            <div>🎮 Jugando...</div>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <div class="title-row">
                <a href="/" class="back-btn">←</a>
                <span class="title">Rasca y Gana</span>
            </div>
            <div class="balance">
                <span>🪙</span>
                <span class="balance-amount" id="balance">{{ $balance }}</span>
            </div>
        </div>

        <div class="game-area">
            <div class="card-wrapper">
                <div class="card-header">
                    <div class="card-title">🎟️ RASCA Y GANA</div>
                    <div class="card-subtitle">¡Rascá para revelar tu premio!</div>
                </div>

                <div class="canvas-wrapper" id="canvasWrapper">
                    <canvas id="scratchCanvas" class="scratch-canvas"></canvas>
                    <div class="prize-display" id="prizeDisplay" style="display: none;">
                        <div class="prize-value" id="prizeValue">0</div>
                        <div class="prize-label">TP</div>
                    </div>
                </div>

                <div class="card-footer" id="cardFooter" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="controls">
            <button class="buy-btn" id="buyBtn" onclick="buyTicket()">
                COMPRAR TICKET (10 TP)
            </button>

            <div class="result-panel" id="resultPanel">
                <span class="result-emoji" id="resultEmoji">🎉</span>
                <span class="result-text" id="resultText">+0 TP</span>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const COST = 10;
        const MAX_SCRATCH_PERCENT = 70;
        
        let balance = {{ $balance }};
        let hasTicket = false;
        let finished = false;
        let prize = 0;
        let ctx = null;
        let canvasWidth = 300;
        let canvasHeight = 150;
        
        window.onload = function() {
            const canvas = document.getElementById('scratchCanvas');
            ctx = canvas.getContext('2d');
            canvas.width = canvasWidth;
            canvas.height = canvasHeight;
            ctx.scale(2, 2);
            canvas.addEventListener('touchstart', onTouchStart);
            canvas.addEventListener('touchmove', onTouchMove);
            canvas.addEventListener('touchend', onTouchEnd);
            canvas.addEventListener('mousedown', onMouseDown);
            canvas.addEventListener('mousemove', onMouseMove);
            canvas.addEventListener('mouseup', onMouseUp);
        };

        async function buyTicket() {
            if (COST > balance || hasTicket) return;
            
            document.getElementById('loading').classList.remove('hidden');
            
            try {
                const res = await fetch(`${API_BASE}/rasca/play`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: 1, bet: COST })
                });
                const data = await res.json();
                
                if (!data.success) {
                    alert(data.message || 'Error');
                    document.getElementById('loading').classList.add('hidden');
                    return;
                }
                
                balance = data.balance;
                prize = data.prize;
                hasTicket = true;
                finished = false;
                
                updateBalance();
                drawCover();
                document.getElementById('prizeValue').textContent = prize;
                document.getElementById('cardFooter').style.display = 'block';
                document.getElementById('resultPanel').classList.remove('show');
                document.getElementById('buyBtn').textContent = 'RASCANDO...';
                document.getElementById('buyBtn').disabled = true;
                
            } catch (err) {
                alert('Error de conexión');
            }
            
            document.getElementById('loading').classList.add('hidden');
        }

        function updateBalance() {
            document.getElementById('balance').textContent = balance;
        }

        function drawCover() {
            ctx.fillStyle = '#5a5a7a';
            ctx.fillRect(0, 0, canvasWidth, canvasHeight);
            
            ctx.fillStyle = '#8888a0';
            ctx.font = 'bold 16px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('ARRASÁ PARA RASCAR', canvasWidth / 2, canvasHeight / 2);
        }

        function scratch(x, y) {
            if (!hasTicket || finished) return;
            
            const rect = document.getElementById('scratchCanvas').getBoundingClientRect();
            const canvasX = (x - rect.left) * (canvasWidth / rect.width);
            const canvasY = (y - rect.top) * (canvasHeight / rect.height);
            
            ctx.globalCompositeOperation = 'destination-out';
            ctx.beginPath();
            ctx.arc(canvasX, canvasY, 20, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalCompositeOperation = 'source-over';
            
            document.getElementById('prizeDisplay').style.display = 'flex';
            
            checkProgress();
        }

        function checkProgress() {
            const imageData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
            const data = imageData.data;
            
            let transparent = 0;
            const total = data.length / 4;
            
            for (let i = 3; i < data.length; i += 16) {
                if (data[i] === 0) transparent++;
            }
            
            const percent = Math.round((transparent / (total / 4)) * 100);
            document.getElementById('progressFill').style.width = percent + '%';
            
            if (percent >= MAX_SCRATCH_PERCENT) {
                finishScratching();
            }
        }

        function finishScratching() {
            if (finished) return;
            finished = true;
            
            balance += prize;
            updateBalance();
            
            const profit = prize - COST;
            document.getElementById('resultEmoji').textContent = profit > 0 ? '🎉' : '😢';
            document.getElementById('resultText').textContent = (profit > 0 ? '+' : '') + profit + ' TP';
            document.getElementById('resultText').className = 'result-text ' + (profit > 0 ? 'win' : 'lose');
            document.getElementById('resultPanel').classList.add('show');
            
            hasTicket = false;
            document.getElementById('buyBtn').textContent = 'COMPRAR TICKET (10 TP)';
            document.getElementById('buyBtn').disabled = false;
            document.getElementById('cardFooter').style.display = 'none';
            document.getElementById('prizeDisplay').style.display = 'none';
            
            setTimeout(() => drawCover(), 500);
        }

        let isScratching = false;

        function onTouchStart(e) { e.preventDefault(); isScratching = true; scratch(e.touches[0].clientX, e.touches[0].clientY); }
        function onTouchMove(e) { e.preventDefault(); if (isScratching) scratch(e.touches[0].clientX, e.touches[0].clientY); }
        function onTouchEnd(e) { isScratching = false; }
        function onMouseDown(e) { isScratching = true; scratch(e.clientX, e.clientY); }
        function onMouseMove(e) { if (isScratching) scratch(e.clientX, e.clientY); }
        function onMouseUp(e) { isScratching = false; }
    </script>
</body>
</html>