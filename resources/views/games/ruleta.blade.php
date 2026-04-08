<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruleta - Toka Games</title>
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
            background: linear-gradient(135deg, #252542 0%, #1e1e38 100%);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #3d3d5c;
            display: flex;
            justify-content: center;
        }

        .slot-container {
            position: relative;
            width: 200px;
            height: 480px;
        }

        .slot-machine {
            position: relative;
            width: 200px;
            height: 470px;
            overflow: hidden;
            border: 4px solid #ffd700;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(255,215,0,0.3);
        }

        .reel {
            display: flex;
            flex-direction: column;
        }
        .reel.spinning {
            transition: transform 5s cubic-bezier(0.05, 0.9, 0.1, 1);
        }

        .reel-symbol {
            width: 200px;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
            color: #fff;
            border-bottom: 2px solid #1a1a2e;
        }

        .slot-overlay {
            position: absolute;
            left: 0;
            right: 0;
            height: 40px;
            z-index: 5;
            pointer-events: none;
        }
        .slot-overlay.top {
            top: 0;
            background: linear-gradient(to bottom, #0d0d1a, transparent);
        }
        .slot-overlay.bottom {
            bottom: 0;
            background: linear-gradient(to top, #0d0d1a, transparent);
        }

        .slot-line {
            position: absolute;
            top: 50%;
            left: -5px;
            right: -5px;
            transform: translateY(-50%);
            z-index: 10;
        }
        .slot-line::before, .slot-line::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 0;
            height: 0;
            border-top: 15px solid transparent;
            border-bottom: 15px solid transparent;
        }
        .slot-line::before {
            left: 0;
            border-right: 20px solid #ffd700;
            transform: translateY(-50%) rotate(180deg);
        }
        .slot-line::after {
            right: 0;
            border-left: 20px solid #ffd700;
            transform: translateY(-50%);
        }

        .win-popup {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            width: 280px;
            height: 80px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 0 30px rgba(0,0,0,0.7);
        }
        .win-popup.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        .win-text {
            font-size: 32px;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 2rpx 6px rgba(0,0,0,0.6);
        }

        .controls { margin-bottom: 20px; }

        .spin-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .spin-btn:disabled { background: #3d3d5c; cursor: not-allowed; }
        .spin-btn:not(:disabled):hover { background: linear-gradient(135deg, #5a6fd6 0%, #5e3d85 100%); }

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
        <div class="loading-content">🎲 Girando...</div>
    </div>

    <div class="container">
        <div class="header">
            <div class="title-row">
                <a href="/" class="back-btn">←</a>
                <span class="title">Ruleta</span>
            </div>
            <div class="balance">
                <span>🪙</span>
                <span class="balance-amount" id="balance">{{ $balance }}</span>
            </div>
        </div>

        <div class="game-board">
            <div class="slot-container">
                <div class="slot-machine">
                    <div class="reel-wrapper">
                        <div class="reel" id="reel"></div>
                    </div>
                    <div class="slot-overlay top"></div>
                    <div class="slot-overlay bottom"></div>
                    <div class="slot-line"></div>
                    <div class="win-popup" id="winPopup">
                        <span class="win-text" id="winText">+0 TP</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="controls">
            <button class="spin-btn" id="spinBtn" onclick="spinWheel()">
                <span>🎲</span>
                <span>GIRAR</span>
            </button>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const BET = 10;
        
        const SYMBOLS = [
            { text: '0', color: '#131313', value: 0 },
            { text: '10', color: '#51aa5f', value: 10 },
            { text: '20', color: '#80c2dc', value: 20 },
            { text: '50', color: '#3388c6', value: 50 },
            { text: '100', color: '#dd6860', value: 100 },
            { text: '200', color: '#e37d6b', value: 200 },
            { text: '500', color: '#22345a', value: 500 },
            { text: '1000', color: '#c29706', value: 1000 }
        ];
        
        let balance = {{ $balance }};
        let spinning = false;
        let canSpin = true;

        window.onload = function() {
            generateReel(40);
        };

        function generateReel(count) {
            const reel = document.getElementById('reel');
            reel.innerHTML = '';
            
            for (let i = 0; i < count; i++) {
                const symbol = SYMBOLS[i % SYMBOLS.length];
                const div = document.createElement('div');
                div.className = 'reel-symbol';
                div.style.background = symbol.color;
                div.textContent = symbol.text;
                reel.appendChild(div);
            }
        }

        async function spinWheel() {
            if (spinning || BET > balance) return;
            
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('spinBtn').disabled = true;
            
            try {
                const res = await fetch(`${API_BASE}/ruleta/play`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: 1, bet: BET })
                });
                const data = await res.json();
                
                if (!data.success) {
                    alert(data.message || 'Error');
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('spinBtn').disabled = false;
                    return;
                }
                
                balance = data.balance;
                updateBalance();
                animateSpin(data.win_index, data.prize);
                
            } catch (err) {
                alert('Error de conexión');
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('spinBtn').disabled = false;
            }
        }

        function animateSpin(winIndex, prize) {
            const reel = document.getElementById('reel');
            const symbolHeight = 160;
            const winPosition = 24 + winIndex;
            const targetOffset = -(winPosition - 1) * symbolHeight;
            
            reel.style.transition = 'none';
            reel.style.transform = 'translateY(0)';
            
            setTimeout(() => {
                spinning = true;
                reel.classList.add('spinning');
                reel.style.transform = `translateY(${targetOffset}px)`;
                
                setTimeout(() => {
                    document.getElementById('winPopup').style.background = SYMBOLS[winIndex].color;
                    document.getElementById('winText').textContent = (prize > 0 ? '+' : '') + prize + ' TP';
                    document.getElementById('winPopup').classList.add('show');
                    
                    setTimeout(() => {
                        document.getElementById('winPopup').classList.remove('show');
                        
                        setTimeout(() => {
                            balance = balance + prize;
                            updateBalance();
                            
                            reel.classList.remove('spinning');
                            reel.style.transition = 'none';
                            reel.style.transform = 'translateY(0)';
                            
                            spinning = false;
                            canSpin = true;
                            document.getElementById('spinBtn').disabled = false;
                            document.getElementById('loading').classList.add('hidden');
                            
                            generateReel(40);
                        }, 500);
                    }, 2000);
                }, 5000);
            }, 50);
        }

        function updateBalance() {
            document.getElementById('balance').textContent = balance;
        }
    </script>
</body>
</html>