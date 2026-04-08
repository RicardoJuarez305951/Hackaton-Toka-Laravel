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

const API_BASE = 'http://localhost:8000/api';

Page({
  data: {
    userId: 1,
    balance: 500,
    bet: 10,
    spinning: false,
    canSpin: true,
    reelOffset: 0,
    reelSymbols: [],
    showResult: false,
    resultSymbol: null,
    prizeAmount: 0
  },
  onLoad() {
    this.generateReelSymbols(40);
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
  generateReelSymbols(count) {
    const reel = [];
    for (let i = 0; i < count; i++) {
      reel.push(SYMBOLS[i % SYMBOLS.length]);
    }
    this.setData({ reelSymbols: reel });
  },

  goBack() {
    my.navigateBack();
  },
  spinWheel() {
    if (this.data.spinning || this.data.bet > this.data.balance) return;
    
    my.showLoading({ content: 'Girando...' });

    my.request({
      url: `${API_BASE}/ruleta/play`,
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
          spinning: false, 
          canSpin: false, 
          reelOffset: 0, 
          showResult: false 
        }, () => {
          setTimeout(() => {
            const winIndex = result.win_index;
            const winSymbol = SYMBOLS[winIndex];
            const symbolHeight = 320;
            const winPosition = 24 + winIndex;
            const targetOffset = -(winPosition - 1) * symbolHeight;
            
            this.setData({ 
              spinning: true,
              reelOffset: targetOffset,
              resultSymbol: winSymbol,
              prizeAmount: result.prize
            });
            
            setTimeout(() => {
              this.setData({ showResult: true });
              
              setTimeout(() => {
                this.setData({
                  balance: result.balance,
                  spinning: false,
                  canSpin: true,
                  showResult: false
                });
              }, 1500);
            }, 5000);
          }, 50);
        });
      },
      fail: (err) => {
        my.hideLoading();
        my.showToast({ content: 'Error de conexión', type: 'fail' });
        console.error('API error:', err);
      }
    });
  }
});