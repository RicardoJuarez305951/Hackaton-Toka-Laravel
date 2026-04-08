<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plinko - Toka Games</title>
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

        .game-board {
            background: linear-gradient(180deg, #252542 0%, #1e1e38 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #3d3d5c;
        }

        .plinko-container {
            width: 100%;
            max-width: 350px;
            height: 350px;
            margin: 0 auto;
            position: relative;
            background: radial-gradient(circle at 50% 30%, #252550 0%, #15152a 100%);
            border-radius: 12px;
            border: 2px solid #3d3d5c;
        }

        .peg {
            position: absolute;
            width: 12px;
            height: 12px;
            background: radial-gradient(circle at 35% 35%, #fff 0%, #ccc 50%, #888 100%);
            border-radius: 50%;
            transition: transform 0.2s, background 0.2s;
        }
        .peg.hit {
            background: radial-gradient(circle at 35% 35%, #ffd700 0%, #ffaa00 100%);
            transform: scale(1.5);
            box-shadow: 0 0 15px rgba(255,215,0,0.8);
        }

        .ball {
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fffde0 0%, #ffd700 35%, #e6a800 100%);
            box-shadow: 0 0 15px rgba(255,215,0,0.8);
            z-index: 20;
            transform: translate(-50%, -50%);
            transition: top 0.4s ease-in, left 0.4s ease-out;
            display: none;
        }
        .ball.visible { display: block; }
        .ball.bouncing { transform: translate(-50%, -50%) scale(1.2, 0.8); }

        .slots-row {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            gap: 4px;
        }

        .slot {
            flex: 1;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 800;
            color: #fff;
        }
        .slot.gold { background: linear-gradient(180deg, #ffd700 0%, #b8860b 100%); }
        .slot.blue { background: linear-gradient(180deg, #4fc3f7 0%, #0288d1 100%); }
        .slot.green { background: linear-gradient(180deg, #66bb6a 0%, #388e3c 100%); }
        .slot.gray { background: linear-gradient(180deg, #78909c 0%, #455a64 100%); }

        .controls { margin-bottom: 20px; }

        .play-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .play-btn:disabled { background: #3d3d5c; cursor: not-allowed; }
        .play-btn:not(:disabled):hover { background: linear-gradient(135deg, #5a6fd6 0%, #5e3d85 100%); }

        .history-panel {
            background: linear-gradient(135deg, #252542 0%, #1e1e38 100%);
            border-radius: 12px;
            padding: 15px;
            border: 2px solid #3d3d5c;
        }
        .history-title { font-size: 14px; color: #aaa; margin-bottom: 10px; }
        .history-list { display: flex; gap: 10px; }
        .history-item {
            flex: 1;
            background: #1a1a2e;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .history-item.win { border: 2px solid #66bb6a; }
        .history-item.lose { border: 2px solid #ef5350; }
        .history-emoji { font-size: 20px; }
        .history-amount { font-size: 12px; font-weight: 600; }
        .history-item.win .history-amount { color: #66bb6a; }
        .history-item.lose .history-amount { color: #ef5350; }

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
        .loading-content { background: #1a1a2e; padding: 30px; border-radius: 16px; }
    </style>
</head>
<body>
    <div class="loading hidden" id="loading">
        <div class="loading-content">🎮 Jugando...</div>
    </div>

    <div class="container">
        <div class="header">
            <div class="title-row">
                <a href="/" class="back-btn">←</a>
                <span class="title">Plinko</span>
            </div>
            <div class="balance">
                <span>🪙</span>
                <span class="balance-amount" id="balance">{{ $balance }}</span>
            </div>
        </div>

        <div class="game-board">
            <div class="plinko-container" id="plinkoContainer">
                <div class="ball" id="ball"></div>
                <!-- Pegs will be generated by JS -->
                <div class="slots-row">
                    <div class="slot gold">10x</div>
                    <div class="slot blue">3x</div>
                    <div class="slot green">1x</div>
                    <div class="slot gray">0.5x</div>
                    <div class="slot green">1x</div>
                    <div class="slot blue">3x</div>
                    <div class="slot gold">10x</div>
                </div>
            </div>
        </div>

        <div class="controls">
            <button class="play-btn" id="playBtn" onclick="playGame()">
                SOLTAR BOLA
            </button>
        </div>

        <div class="history-panel">
            <div class="history-title">HISTORIAL</div>
            <div class="history-list" id="historyList">
                <div class="history-item">
                    <div class="history-emoji">🎮</div>
                    <div class="history-amount">Sin juega</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const USER_ID = {{ $user->id }};
        const BET = 10;
        const MULTIPLIERS = [10, 3, 1, 0.5, 1, 3, 10];
        
        const BOARD = {
            centerX: 175,
            startY: 20,
            rowStartY: 50,
            rowSpacing: 40,
            bounceX: 25,
            slotsY: 295,
            totalRows: 6
        };
        
        let balance = {{ $balance }};
        let isPlaying = false;
        let canPlay = true;
        let history = [];

        window.onload = function() {
            generatePegs();
        };

        function generatePegs() {
            const container = document.getElementById('plinkoContainer');
            const existingPegs = container.querySelectorAll('.peg');
            existingPegs.forEach(p => p.remove());
            
            for (let row = 0; row < BOARD.totalRows; row++) {
                const pegsInRow = row + 1;
                const rowWidth = pegsInRow * 45;
                const startX = (350 - rowWidth) / 2 + 22;
                
                for (let col = 0; col < pegsInRow; col++) {
                    const peg = document.createElement('div');
                    peg.className = 'peg';
                    peg.style.left = (startX + col * 45) + 'px';
                    peg.style.top = (BOARD.rowStartY + row * BOARD.rowSpacing) + 'px';
                    peg.dataset.row = row;
                    peg.dataset.col = col;
                    container.appendChild(peg);
                }
            }
        }

        async function playGame() {
            if (isPlaying || BET > balance) return;
            
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('playBtn').disabled = true;
            
            try {
                const res = await fetch(`${API_BASE}/plinko/play`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: USER_ID, bet: BET })
                });
                const data = await res.json();
                
                if (!data.success) {
                    alert(data.message || 'Error');
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('playBtn').disabled = false;
                    return;
                }
                
                balance = data.balance;
                updateBalance();
                playSplineAnimation(data.spline, data.slot_index, data.prize);
                
            } catch (err) {
                alert('Error de conexión');
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('playBtn').disabled = false;
            }
        }

        function playSplineAnimation(spline, finalSlot, serverPrize) {
            const ball = document.getElementById('ball');
            const positions = spline.positions || [];
            
            ball.style.display = 'block';
            ball.style.top = BOARD.startY + 'px';
            ball.style.left = BOARD.centerX + 'px';
            
            setTimeout(() => ball.classList.add('visible'), 50);
            
            let step = 0;
            
            function animate() {
                if (step >= positions.length) {
                    ball.style.top = BOARD.slotsY + 'px';
                    
                    setTimeout(() => {
                        const profit = serverPrize - BET;
                        
                        history.unshift({ profit });
                        if (history.length > 5) history.pop();
                        updateHistory();

                        updateBalance();
                        
                        ball.style.display = 'none';
                        ball.classList.remove('visible');
                        document.getElementById('playBtn').disabled = false;
                        document.getElementById('loading').classList.add('hidden');
                        
                        generatePegs();
                    }, 400);
                    return;
                }
                
                const hitCol = positions[step];
                if (step > 0) {
                    const peg = document.querySelector(`.peg[data-row="${step-1}"][data-col="${hitCol}"]`);
                    if (peg) {
                        peg.classList.add('hit');
                        ball.classList.add('bouncing');
                        setTimeout(() => {
                            peg.classList.remove('hit');
                            ball.classList.remove('bouncing');
                        }, 200);
                    }
                }
                
                const currentTop = BOARD.rowStartY + (step * BOARD.rowSpacing);
                const currentLeft = BOARD.centerX + ((hitCol - 3) * BOARD.bounceX);
                
                ball.style.top = currentTop + 'px';
                ball.style.left = currentLeft + 'px';
                
                step++;
                setTimeout(animate, 400);
            }
            
            setTimeout(animate, 400);
        }

        function updateBalance() {
            document.getElementById('balance').textContent = balance;
        }

        function updateHistory() {
            const list = document.getElementById('historyList');
            list.innerHTML = history.map(h => `
                <div class="history-item ${h.profit >= 0 ? 'win' : 'lose'}">
                    <div class="history-emoji">${h.profit >= 0 ? '🎉' : '😢'}</div>
                    <div class="history-amount">${h.profit >= 0 ? '+' : ''}${h.profit}</div>
                </div>
            `).join('') || '<div class="history-item"><div class="history-emoji">🎮</div><div class="history-amount">Sin juega</div></div>';
        }
    </script>
</body>
</html>
