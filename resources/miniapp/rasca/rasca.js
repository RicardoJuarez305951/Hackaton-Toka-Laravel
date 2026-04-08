const MAX_SCRATCH_PERCENT = 70;

const API_BASE = 'http://localhost:8000/api';

Page({
  data: {
    userId: 1,
    balance: 500,
    cost: 10,
    hasTicket: false,
    canBuy: true,
    showResult: false,
    resultProfit: 0,
    prize: 0,
    showPrize: false,
    finished: false,
    scratchedPercent: 0,
    scratchStarted: false,
    isScratching: false
  },
  ctx: null,
  canvasWidth: 600,
  canvasHeight: 300,
  touchStartX: 0,
  touchStartY: 0,
  hasStartedScratching: false,
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
  onCanvasReady(e) {
    const query = my.createSelectorQuery();
    query.select('#scratchCanvas')
      .node()
      .exec((res) => {
        const canvas = res[0].node;
        this.ctx = canvas.getContext('2d');
        const dpr = my.getSystemInfoSync().pixelRatio || 2;
        canvas.width = this.canvasWidth * dpr;
        canvas.height = this.canvasHeight * dpr;
        this.ctx.scale(dpr, dpr);
        this.drawCover();
      });
  },
  goBack() {
    my.navigateBack();
  },
  drawCover() {
    if (!this.ctx) return;
    const ctx = this.ctx;
    const dpr = my.getSystemInfoSync().pixelRatio || 2;
    
    ctx.fillStyle = '#5a5a7a';
    ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
    
    ctx.strokeStyle = '#6d6d8c';
    ctx.lineWidth = 2;
    for (let i = 0; i < 20; i++) {
      const x = Math.random() * this.canvasWidth;
      const y = Math.random() * this.canvasHeight;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x + 30, y + 30);
      ctx.stroke();
    }
    
    ctx.fillStyle = '#8888a0';
    ctx.font = 'bold 24px -apple-system, BlinkMacSystemFont, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('ARRASÁ PARA RASCAR', this.canvasWidth / 2, this.canvasHeight / 2);
  },
  initCanvas() {
    if (!this.ctx) return;
    this.ctx.clearRect(0, 0, this.canvasWidth, this.canvasHeight);
    this.drawCover();
  },
  buyTicket() {
    if (this.data.cost > this.data.balance || this.data.hasTicket) return;
    
    my.showLoading({ content: 'Jugando...' });
    
    my.request({
      url: `${API_BASE}/rasca/play`,
      method: 'POST',
      data: {
        user_id: this.data.userId,
        bet: this.data.cost
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
          hasTicket: true,
          canBuy: false,
          showResult: false,
          resultProfit: 0,
          prize: result.prize,
          showPrize: false,
          finished: false,
          scratchedPercent: 0,
          scratchStarted: false,
          isScratching: false
        });
        
        setTimeout(() => {
          this.initCanvas();
        }, 100);
      },
      fail: (err) => {
        my.hideLoading();
        my.showToast({ content: 'Error de conexión', type: 'fail' });
        console.error('API error:', err);
      }
    });
  },
  onTouchStart(e) {
    if (!this.data.hasTicket || this.data.finished) return;
    
    this.touchStartX = e.touches[0].pageX;
    this.touchStartY = e.touches[0].pageY;
    this.setData({ isScratching: true });
    
    this.scratchAt(e.touches[0].pageX, e.touches[0].pageY);
  },
  onTouchMove(e) {
    if (!this.data.hasTicket || this.data.finished || !this.data.isScratching) return;
    
    this.scratchAt(e.touches[0].pageX, e.touches[0].pageY);
  },
  onTouchEnd(e) {
    if (!this.data.hasTicket || this.data.finished) return;
    
    this.setData({ isScratching: false });
    this.checkScratchProgress();
  },
  scratchAt(x, y) {
    if (!this.ctx) return;
    
    const query = my.createSelectorQuery();
    query.select('#scratchCanvas')
      .boundingClientRect()
      .exec((res) => {
        if (!res[0]) return;
        
        const rect = res[0];
        const canvasX = x - rect.left;
        const canvasY = y - rect.top;
        
        const ctx = this.ctx;
        ctx.globalCompositeOperation = 'destination-out';
        
        ctx.beginPath();
        ctx.arc(canvasX, canvasY, 25, 0, Math.PI * 2);
        ctx.fill();
        
        ctx.globalCompositeOperation = 'source-over';
        
        if (!this.hasStartedScratching) {
          this.hasStartedScratching = true;
          this.setData({ scratchStarted: true, showPrize: true });
        }
      });
  },
  checkScratchProgress() {
    if (!this.ctx) return;
    
    const dpr = my.getSystemInfoSync().pixelRatio || 2;
    const imageData = this.ctx.getImageData(0, 0, this.canvasWidth * dpr, this.canvasHeight * dpr);
    const data = imageData.data;
    
    let transparentPixels = 0;
    const totalPixels = data.length / 4;
    const sampleRate = 4;
    
    for (let i = 3; i < data.length; i += 4 * sampleRate) {
      if (data[i] === 0) {
        transparentPixels++;
      }
    }
    
    const sampledTotal = totalPixels / sampleRate;
    const percent = Math.round((transparentPixels / sampledTotal) * 100);
    
    this.setData({ scratchedPercent: percent });
    
    if (percent >= MAX_SCRATCH_PERCENT) {
      this.finishScratching();
    }
  },
  finishScratching() {
    if (this.data.finished) return;
    
    this.setData({ finished: true });
    
    setTimeout(() => {
      const profit = this.data.prize - this.data.cost;
      this.setData({
        // Balance already comes finalized from backend response
        balance: this.data.balance,
        hasTicket: false,
        canBuy: true,
        showResult: true,
        resultProfit: profit
      });
    }, 500);
  }
});
