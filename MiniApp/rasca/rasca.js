Page({
  data: {
    balance: 500,
    cost: 10,

    // Game phase: 'idle' | 'playing' | 'finished'
    phase: 'idle',

    // Current tier info (0 = none reached yet)
    currentTier: 0,
    tierName: '',
    tierClass: '',
    prize: 0,

    // Animation class on the chest ('', 'bounce', 'fail-shake')
    chestAnim: '',

    // Result
    resultProfit: 0,

    // Cooldown to prevent double-taps
    tapping: false,
  },

  rewardTiers: [
    { name: 'Común',      prob: 1.00, reward: 2,   class: 'common'   },
    { name: 'Poco Común', prob: 0.70, reward: 10,  class: 'uncommon' },
    { name: 'Raro',       prob: 0.40, reward: 25,  class: 'rare'     },
    { name: 'Épico',      prob: 0.20, reward: 60,  class: 'epic'     },
    { name: 'Legendario', prob: 0.10, reward: 200, class: 'legendary'},
  ],

  goBack() {
    my.navigateBack();
  },

  // ── Iniciar el juego, cobra los 10 TC ──
  startGame() {
    if (this.data.balance < this.data.cost || this.data.phase !== 'idle') return;

    this.setData({
      balance: this.data.balance - this.data.cost,
      phase: 'playing',
      currentTier: 0,
      tierName: '',
      tierClass: '',
      prize: 0,
      chestAnim: '',
      resultProfit: 0,
    });
  },

  // ── El jugador toca el cofre ──
  tapChest() {
    if (this.data.phase !== 'playing' || this.data.tapping) return;

    const nextIndex = this.data.currentTier; // next tier to attempt (0-based index)

    // Already at max tier?
    if (nextIndex >= this.rewardTiers.length) {
      this.finishGame();
      return;
    }

    // Lock against double-taps
    this.setData({ tapping: true });

    const tier = this.rewardTiers[nextIndex];
    const luck = Math.random();
    const success = luck <= tier.prob;

    if (success) {
      // Advance to this tier
      this.setData({
        currentTier: nextIndex + 1,
        tierName: tier.name,
        tierClass: tier.class,
        prize: tier.reward,
        chestAnim: 'bounce',
      });

      // Clear animation class after it plays, then allow next tap
      setTimeout(() => {
        this.setData({ chestAnim: '', tapping: false });

        // If we just reached max tier, auto-finish
        if (nextIndex + 1 >= this.rewardTiers.length) {
          this.finishGame();
        }
      }, 600);

    } else {
      // Failed — play fail shake then finish
      this.setData({ chestAnim: 'fail-shake' });
      setTimeout(() => {
        this.setData({ chestAnim: '', tapping: false });
        this.finishGame();
      }, 700);
    }
  },

  // ── El jugador decide cobrar lo que tiene ──
  surrender() {
    if (this.data.phase !== 'playing') return;
    this.finishGame();
  },

  // ── Finalizar el juego y mostrar resultado ──
  finishGame() {
    const prize = this.data.prize;
    const profit = prize - this.data.cost;

    this.setData({
      phase: 'finished',
      chestAnim: '',
      balance: this.data.balance + prize,
      resultProfit: profit,
    });
  },

  // ── Volver a jugar ──
  resetGame() {
    this.setData({
      phase: 'idle',
      currentTier: 0,
      tierName: '',
      tierClass: '',
      prize: 0,
      chestAnim: '',
      resultProfit: 0,
    });
  },
});