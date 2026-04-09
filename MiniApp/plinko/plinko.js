// Layout constants (must match plinko.acss exactly)
const BOARD = {
  centerX: 325,
  startY: 30,       // above first row
  rowStartY: 100,    // center of first peg row (60 margin + 40 half-row)
  rowSpacing: 80,    // each row is 80rpx tall
  bounceX: 45,       // half of peg-to-peg distance (~90rpx)
  slotsY: 565,       // just above the slots
  totalRows: 6
};

// Build the initial pegs array: row r has (r+1) pegs
function buildPegs() {
  const pegs = [];
  for (let r = 0; r < BOARD.totalRows; r++) {
    const row = [];
    for (let c = 0; c <= r; c++) {
      row.push({ hit: false });
    }
    pegs.push(row);
  }
  return pegs;
}

Page({
  data: {
    balance: 500,
    bet: 10,
    isPlaying: false,
    canPlay: true,
    coinTop: BOARD.startY,
    coinLeft: BOARD.centerX,
    showCoin: false,
    ballBouncing: false,
    history: [],
    multipliers: [4.5, 1.8, 0.8, 0.4, 0.8, 1.8, 4.5],
    pegs: buildPegs()
  },
  
  physicsInterval: null,

  goBack() {
    if (this.physicsInterval) clearInterval(this.physicsInterval);
    my.navigateBack();
  },

  // Light up the peg at [row][col] briefly
  _triggerPegHit(row, col) {
    const pegs = this.data.pegs;
    pegs[row][col] = { hit: true };
    this.setData({ pegs, ballBouncing: true });

    setTimeout(() => {
      const pegs2 = this.data.pegs;
      if (pegs2[row] && pegs2[row][col]) {
        pegs2[row][col] = { hit: false };
      }
      this.setData({ pegs: pegs2, ballBouncing: false });
    }, 150);
  },

  playGame() {
    if (this.data.isPlaying || this.data.bet > this.data.balance) return;

    if (this.physicsInterval) clearInterval(this.physicsInterval);

    // Initial setup
    this.setData({
      balance: this.data.balance - this.data.bet,
      isPlaying: true,
      canPlay: false,
      showCoin: true,
      coinTop: BOARD.startY,
      coinLeft: BOARD.centerX,
      pegs: buildPegs()
    });

    // 1. Pre-calculate the path (sequence of columns hit per row)
    const path = [];
    let currentCol = 0;
    for (let r = 0; r < BOARD.totalRows; r++) {
      path.push(currentCol);
      // Decide next: 50% chance to go right (+1 to col index for next row), 50% to stay left (same col index)
      const goRight = Math.random() > 0.5;
      if (goRight) {
        currentCol++;
      }
    }
    const finalSlot = currentCol; // 0 to 6

    // 2. Physics simulation states
    let ballX = BOARD.centerX;
    let ballY = BOARD.startY;
    let vx = 0;
    let vy = 0;
    const gravity = 1.0; 
    
    let currentRow = 0;
    const targetFrames = 18; // Approx frames between pegs (at 16ms/frame = ~300ms)

    // Delay before starting drop
    setTimeout(() => {
      
      // Setup initial jump to first peg
      vy = -2;
      let targetX = BOARD.centerX;
      
      this.physicsInterval = setInterval(() => {
        // Apply physics
        vy += gravity;
        ballY += vy;
        ballX += vx;

        // Check if we reached or passed the target row's Y position
        const targetY = BOARD.rowStartY + (currentRow * BOARD.rowSpacing) - 10; // slightly above center to hit
        
        if (currentRow < BOARD.totalRows && ballY >= targetY) {
          // HIT THE PEG
          ballY = targetY; // snap to hit point
          
          const hitCol = path[currentRow];
          this._triggerPegHit(currentRow, hitCol);
          
          currentRow++;
          
          if (currentRow < BOARD.totalRows) {
            // Setup bounce towards NEXT peg
            const nextHitCol = path[currentRow];
            
            // Determine X offset based on column index
            // Col 0 is far left, Col max is far right
            // The X center of the pyramid moves out. 
            // In the previous logic, every right choice added bounceX.
            // So X at row R, column C = centerX - (R * bounceX) + (C * 2 * bounceX)
            const nextTargetX = BOARD.centerX - (currentRow * BOARD.bounceX) + (nextHitCol * 2 * BOARD.bounceX);
            
            // Jitter for organic feel
            const jitterX = (Math.random() - 0.5) * 10;
            
            // We want to reach nextTargetX in roughly 'targetFrames'
            vx = (nextTargetX - ballX + jitterX) / targetFrames;
            
            // Bounce up (elasticity + randomness)
            vy = -8 - (Math.random() * 3);
          } else {
            // Final bounce towards the slot
            const finalX = BOARD.centerX - (BOARD.totalRows * BOARD.bounceX) + (finalSlot * 2 * BOARD.bounceX);
            vx = (finalX - ballX) / 15;
            vy = -5; // smaller bounce at the end
          }
        }

        // Check if fell into the slots area
        if (ballY >= BOARD.slotsY) {
          clearInterval(this.physicsInterval);
          this.physicsInterval = null;
          
          ballY = BOARD.slotsY;
          this.setData({ coinTop: ballY, coinLeft: ballX });

          // Process payout
          setTimeout(() => {
            const multiplier = this.data.multipliers[finalSlot];
            const winnings = Math.floor(this.data.bet * multiplier);
            const profit = winnings - this.data.bet;

            const newHistory = [{ profit }, ...this.data.history].slice(0, 5);

            this.setData({
              balance: this.data.balance + winnings,
              isPlaying: false,
              canPlay: true,
              showCoin: false,
              history: newHistory
            });
          }, 300);
          return; // end interval
        }

        // Render Frame
        this.setData({ coinTop: ballY, coinLeft: ballX });

      }, 16); // ~60 FPS
      
    }, 200);
  }
});
