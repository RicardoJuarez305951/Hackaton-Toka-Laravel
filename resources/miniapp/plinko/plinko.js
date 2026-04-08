// Layout constants (must match plinko.acss exactly)
const BOARD = {
  centerX: 325,
  startY: 30,
  rowStartY: 100,
  rowSpacing: 80,
  bounceX: 45,
  slotsY: 565,
  totalRows: 6
};

const API_BASE = 'http://localhost:8000/api';

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
    userId: 1,
    balance: 500,
    bet: 10,
    isPlaying: false,
    canPlay: true,
    coinTop: BOARD.startY,
    coinLeft: BOARD.centerX,
    showCoin: false,
    ballBouncing: false,
    history: [],
    multipliers: [10, 3, 1, 0.5, 1, 3, 10],
    pegs: buildPegs()
  },
  onLoad() {
    this.loadBalance();
  },
  loadBalance() {
    my.request({
      url: `${API_BASE}/user/${this.data.userId}/balance`,
      method: 'GET',
      success: (res) => {
        if (res.data.success) {
          this.setData({ balance: res.data.coins });
        }
      },
      fail: () => {
        console.error('Error loading balance');
      }
    });
  },
  goBack() {
    my.navigateBack();
  },
  _triggerPegHit(row, col) {
    const pegs = this.data.pegs;
    pegs[row][col] = { hit: true };
    this.setData({ pegs, ballBouncing: true });

    setTimeout(() => {
      const pegs2 = this.data.pegs;
      pegs2[row][col] = { hit: false };
      this.setData({ pegs: pegs2, ballBouncing: false });
    }, 280);
  },
  playGame() {
    if (this.data.isPlaying || this.data.bet > this.data.balance) return;

    my.showLoading({ content: 'Jugando...' });

    my.request({
      url: `${API_BASE}/plinko/play`,
      method: 'POST',
      data: {
        user_id: this.data.userId,
        bet: this.data.bet
      },
      success: (res) => {
        my.hideLoading();
        
        if (!res.data.success) {
          my.showToast({ content: res.data.message || 'Error', type: 'fail' });
          return;
        }
        
        const result = res.data;
        
        this.setData({
          balance: result.balance,
          isPlaying: true,
          canPlay: false,
          showCoin: false,
          pegs: buildPegs()
        });

        this.playSplineAnimation(result.spline, result.slot_index, result.prize);
      },
      fail: (err) => {
        my.hideLoading();
        my.showToast({ content: 'Error de conexión', type: 'fail' });
        console.error('API error:', err);
      }
    });
  },
  playSplineAnimation(spline, finalSlot, serverPrize) {
    setTimeout(() => {
      this.setData({ coinTop: BOARD.startY, coinLeft: BOARD.centerX });
      setTimeout(() => {
        this.setData({ showCoin: true });
      }, 50);
    }, 50);

    let stepIndex = 0;
    const positions = spline.positions || [];

    const animateStep = () => {
      if (stepIndex >= positions.length) {
        this.setData({ coinTop: BOARD.slotsY });
        
        setTimeout(() => {
          const profit = serverPrize - this.data.bet;

          const newHistory = [{ profit }, ...this.data.history].slice(0, 5);

          this.setData({
            // Balance already comes finalized from backend response
            balance: this.data.balance,
            isPlaying: false,
            canPlay: true,
            showCoin: false,
            history: newHistory
          });
        }, 500);
        return;
      }

      const hitCol = positions[stepIndex];
      if (stepIndex > 0) {
        this._triggerPegHit(stepIndex - 1, hitCol);
      }

      const currentTop = BOARD.rowStartY + ((stepIndex) * BOARD.rowSpacing);
      const currentLeft = BOARD.centerX + ((hitCol - 3) * BOARD.bounceX);
      
      this.setData({ coinTop: currentTop, coinLeft: currentLeft });
      stepIndex++;

      const nextDelay = 480;
      setTimeout(animateStep, nextDelay);
    };

    setTimeout(animateStep, 400);
  }
});
