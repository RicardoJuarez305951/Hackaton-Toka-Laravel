const SYMBOLS = [
  { text: '0', color: '#131313', value: 0 },
  { text: '10', color: '#51aa5f', value: 10 },
  { text: '20', color: '#80c2dc', value: 20 },
  { text: '50', color: '#3388c6', value: 50 },
  { text: '100', color: '#0f6b65', value: 100 },
  { text: '200', color: '#e37d6b', value: 200 },
  { text: '500', color: '#22345a', value: 500 },
  { text: '1000', color: '#c29706', value: 1000 }
];

Page({
  data: {
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
    
    // Deduct bet immediately and reset reel directly to 0 (no animation)
    this.setData({ 
      balance: this.data.balance - this.data.bet,
      spinning: false, 
      canSpin: false, 
      reelOffset: 0, 
      showResult: false 
    }, () => {
      // Use short delay to wait for reelOffset=0 to render before reapplying transition
      setTimeout(() => {
        const winIndex = Math.floor(Math.random() * SYMBOLS.length);
        const winSymbol = SYMBOLS[winIndex];
        const symbolHeight = 320;
        
        // Pick element further down to ensure a long spin (set 3: indices 24-31)
        const winPosition = 24 + winIndex;
        // The container displays 3 items. If we offset so winPosition - 1 is at top (translating by -(winPosition - 1) * height),
        // the winPosition item will be in the middle spanning [120, 240], exactly centered at 180.
        const targetOffset = -(winPosition-1 ) * symbolHeight;
        
        const prize = winSymbol.value;
        
        this.setData({ 
          spinning: true,
          reelOffset: targetOffset,
          resultSymbol: winSymbol,
          prizeAmount: prize
        });
        
        // Let the 5000ms transition run fully before showing the result text
        setTimeout(() => {
          this.setData({ showResult: true });
          
          // Display the result for 1.5 seconds, then allow spinning again.
          setTimeout(() => {
            this.setData({
              balance: this.data.balance + prize,
              spinning: false,
              canSpin: true,
              showResult: false
              // We intentionally do not reset reelOffset here so it stays put until the next spin
            });
          }, 1500);
        }, 5000);
      }, 50);
    });
  }
});