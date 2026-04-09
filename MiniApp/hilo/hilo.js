const RANKS = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
const SUITS = ['♠','♥','♦','♣'];
const SUIT_COLORS = { '♠': 'black', '♥': 'red', '♦': 'red', '♣': 'black' };
const HOUSE_EDGE = 0.9; // 10% house edge

function randomCard() {
  const rankIndex = Math.floor(Math.random() * 13); // 0-12
  const suit = SUITS[Math.floor(Math.random() * 4)];
  return {
    rank: RANKS[rankIndex],
    value: rankIndex + 2, // 2-14
    suit: suit,
    color: SUIT_COLORS[suit]
  };
}

function calcMultiplier(cardValue, direction) {
  // How many of the 12 other values satisfy the guess?
  let winning;
  if (direction === 'higher') {
    winning = 14 - cardValue; // values from cardValue+1 to 14
  } else {
    winning = cardValue - 2;  // values from 2 to cardValue-1
  }
  if (winning <= 0) return 0; // impossible bet
  // Fair multiplier = 12/winning, then apply house edge
  return Math.round(HOUSE_EDGE * (12 / winning) * 100) / 100;
}

Page({
  data: {
    balance: 500,
    cost: 10,
    isPlaying: false,
    currentCard: null,
    nextCard: null,
    streak: 0,
    currentMultiplier: 1,
    potentialWin: 10,
    higherMult: 0,
    lowerMult: 0,
    canHigher: false,
    canLower: false,
    showResult: false,
    resultWin: false,
    resultAmount: 0,
    flipping: false,
    resultMessage: ''
  },
  goBack() {
    my.navigateBack();
  },
  startGame() {
    if (this.data.isPlaying || this.data.cost > this.data.balance) return;
    
    const card = randomCard();
    const hMult = calcMultiplier(card.value, 'higher');
    const lMult = calcMultiplier(card.value, 'lower');
    
    this.setData({
      balance: this.data.balance - this.data.cost,
      isPlaying: true,
      currentCard: card,
      nextCard: null,
      streak: 0,
      currentMultiplier: 1,
      potentialWin: this.data.cost,
      higherMult: hMult,
      lowerMult: lMult,
      canHigher: hMult > 0,
      canLower: lMult > 0,
      showResult: false,
      flipping: false,
      resultMessage: ''
    });
  },
  guess(e) {
    if (!this.data.isPlaying || this.data.flipping) return;
    
    const direction = e.currentTarget.dataset.direction;
    const mult = direction === 'higher' ? this.data.higherMult : this.data.lowerMult;
    if (mult <= 0) return;
    
    const next = randomCard();
    // Avoid same value — redraw
    let finalCard = next;
    let attempts = 0;
    while (finalCard.value === this.data.currentCard.value && attempts < 20) {
      finalCard = randomCard();
      attempts++;
    }
    
    // If still same after 20 tries, treat as loss
    const won = finalCard.value !== this.data.currentCard.value && (
      (direction === 'higher' && finalCard.value > this.data.currentCard.value) ||
      (direction === 'lower' && finalCard.value < this.data.currentCard.value)
    );
    
    // Show the card with flip animation
    this.setData({ flipping: true, nextCard: finalCard });
    
    setTimeout(() => {
      if (won) {
        const newMult = Math.round(this.data.currentMultiplier * mult * 100) / 100;
        const newPotential = Math.round(this.data.cost * newMult * 100) / 100;
        const newStreak = this.data.streak + 1;
        
        const hMult = calcMultiplier(finalCard.value, 'higher');
        const lMult = calcMultiplier(finalCard.value, 'lower');
        
        this.setData({
          currentCard: finalCard,
          nextCard: null,
          streak: newStreak,
          currentMultiplier: newMult,
          potentialWin: newPotential,
          higherMult: hMult,
          lowerMult: lMult,
          canHigher: hMult > 0,
          canLower: lMult > 0,
          flipping: false
        });
      } else {
        // Lost!
        this.setData({
          currentCard: finalCard,
          nextCard: null,
          isPlaying: false,
          flipping: false,
          showResult: true,
          resultWin: false,
          resultAmount: this.data.cost,
          resultMessage: '¡Perdiste ' + this.data.cost + ' TP!'
        });
      }
    }, 800);
  },
  cashOut() {
    if (!this.data.isPlaying || this.data.streak < 1 || this.data.flipping) return;
    
    const winnings = Math.floor(this.data.potentialWin);
    
    this.setData({
      balance: this.data.balance + winnings,
      isPlaying: false,
      showResult: true,
      resultWin: true,
      resultAmount: winnings,
      resultMessage: '¡Ganaste ' + winnings + ' TP!'
    });
  }
});
