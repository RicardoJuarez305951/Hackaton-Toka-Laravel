<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toka Games - Mini App Simulator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #fff;
        }
        .container { padding: 20px; max-width: 500px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 15px;
            background: linear-gradient(135deg, #2d2d44 0%, #1a1a2e 100%);
            border-radius: 15px;
            border: 2px solid #3d3d5c;
        }
        .logo { font-size: 24px; }
        .balance-display {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1a1a2e;
            padding: 10px 20px;
            border-radius: 20px;
        }
        .balance-amount { font-size: 20px; font-weight: 700; color: #ffd700; }
        
        .section-title {
            font-size: 18px;
            color: #aaa;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .games-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .game-card {
            background: linear-gradient(135deg, #2a2a4a 0%, #1e1e38 100%);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid #3d3d5c;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, border-color 0.2s;
        }
        .game-card:hover {
            transform: translateY(-2px);
            border-color: #ffd700;
        }
        .game-info h3 { font-size: 20px; margin-bottom: 5px; }
        .game-info p { font-size: 14px; color: #aaa; }
        .game-icon { font-size: 40px; }
        
        .debug-panel {
            margin-top: 40px;
            padding: 20px;
            background: #252542;
            border-radius: 16px;
            border: 2px solid #f59e0b;
        }
        .debug-title {
            font-size: 16px;
            font-weight: 700;
            color: #f59e0b;
            margin-bottom: 15px;
        }
        .debug-form {
            display: flex;
            gap: 10px;
        }
        .debug-form input {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #3d3d5c;
            background: #1a1a2e;
            color: #fff;
            font-size: 16px;
        }
        .debug-form button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .debug-form button:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        }
        
        .message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        .message.success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .message.error { background: rgba(239, 83, 80, 0.2); color: #ef5350; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">💎 <strong>TitanDev</strong></div>
            <div class="balance-display">
                <span>🪙</span>
                <span class="balance-amount">{{ $user->coins }} TP</span>
            </div>
        </div>

        <div class="section-title">JUEGA AHORA</div>
        
        <div class="games-grid">
            <a href="/plinko" class="game-card">
                <div class="game-info">
                    <h3>Plinko</h3>
                    <p>Hasta 4.2x de multiplicador</p>
                </div>
                <div class="game-icon">🎯</div>
            </a>
            
            <a href="/rasca" class="game-card">
                <div class="game-info">
                    <h3>Rasca y Gana</h3>
                    <p>Hasta 20x de multiplicador</p>
                </div>
                <div class="game-icon">🎫</div>
            </a>
            
            <a href="/ruleta" class="game-card">
                <div class="game-info">
                    <h3>Ruleta</h3>
                    <p>Hasta 2.4x de multiplicador</p>
                </div>
                <div class="game-icon">🎰</div>
            </a>
            
            <a href="/hilo" class="game-card">
                <div class="game-info">
                    <h3>Hilo</h3>
                    <p>Balatro balatrez</p>
                </div>
                <div class="game-icon">🃏</div>
            </a>

            <a href="/goldentree" class="game-card">
                <div class="game-info">
                    <h3>Goldentree</h3>
                    <p>Cuida tu Árbol</p>
                </div>
                <div class="game-icon">🌲</div>
            </a>
        </div>

        <div class="debug-panel">
            <div class="debug-title">🛠️ DEBUG - Agregar Coins</div>
            <form class="debug-form" method="POST" action="/debug/coins">
                @csrf
                <input type="number" name="amount" value="1000" min="1" placeholder="Cantidad">
                <button type="submit">Agregar</button>
            </form>
            @if(session('message'))
                <div class="message {{ session('message_type') }}">
                    {{ session('message') }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>
