// ── Stage Config ──
// rate = TP generated per 30 seconds
// threshold = cumulative active seconds to reach this stage
const STAGES = [
  { name: 'Semilla',         rate: 1,  threshold: 0 },
  { name: 'Brote',           rate: 1,  threshold: 300 },      // 5 min
  { name: 'Plántula',        rate: 1,  threshold: 900 },      // 15 min
  { name: 'Arbusto',         rate: 2,  threshold: 1800 },     // 30 min
  { name: 'Árbol Joven',     rate: 2,  threshold: 3600 },     // 1h
  { name: 'Árbol Fuerte',    rate: 3,  threshold: 7200 },     // 2h
  { name: 'Árbol Grande',    rate: 4,  threshold: 14400 },    // 4h
  { name: 'Árbol Frondoso',  rate: 5,  threshold: 28800 },    // 8h
  { name: 'Árbol Dorado',    rate: 6,  threshold: 57600 },    // 16h
  { name: 'Árbol Místico',   rate: 7,  threshold: 115200 },   // 32h
];

const EVENTS = [
  { id: 'drought',  name: 'Sequía',      emoji: '🌵', action: 'Regar el árbol' },
  { id: 'plague',   name: 'Plaga',       emoji: '🐛', action: 'Fumigar' },
  { id: 'storm',    name: 'Tormenta',    emoji: '⛈️', action: 'Proteger' },
  { id: 'leaves',   name: 'Hojas Secas', emoji: '🍂', action: 'Podar' },
];

const EVENT_INTERVAL = 4 * 3600; // Check for event every 4 hours minimum
const GENERATION_INTERVAL = 30;   // Generate TP every 30 seconds
const EVENT_TIMEOUT = 60;         // Seconds to respond before penalty

function getDefaultData() {
  return {
    stage: 0,
    growthSeconds: 0,
    bankedTP: 0,
    lastUpdate: Date.now(),
    lastEventCheck: Date.now(),
    activeEvent: null,
    totalGenerated: 0,
    eventsAttended: 0,
    eventTimer: 0,
    extraPauseSeconds: 0
  };
}

function getStageForSeconds(seconds) {
  let stage = 0;
  for (let i = STAGES.length - 1; i >= 0; i--) {
    if (seconds >= STAGES[i].threshold) {
      stage = i;
      break;
    }
  }
  return stage;
}

Page({
  data: {
    balance: 500,
    stage: 0,
    stageName: STAGES[0].name,
    stageRate: STAGES[0].rate,
    growthSeconds: 0,
    bankedTP: 0,
    totalGenerated: 0,
    eventsAttended: 0,
    activeEvent: null,
    // UI
    progressPercent: 0,
    nextStageName: STAGES[1].name,
    timeToNext: '',
    isMaxStage: false,
    canCobrar: false,
    canRegar: false,
    generating: false,
    // Commission
    commissionTP: 0,
    netTP: 0,
    // Event timer
    eventTimer: 0,
    extraPauseSeconds: 0
  },
  _timer: null,
  _saveData: null,

  goBack() {
    my.navigateBack();
  },

  onShow() {
    this.loadData();
    this.processOfflineTime();
    this.updateUI();
    this.startTimer();
  },

  onHide() {
    this.stopTimer();
    this.saveData();
  },

  // ── Persistence ──
  loadData() {
    try {
      const res = my.getStorageSync({ key: 'goldentree_data' });
      if (res && res.data) {
        this._saveData = res.data;
        // Backwards compat: ensure new fields exist
        if (this._saveData.eventTimer === undefined) this._saveData.eventTimer = 0;
        if (this._saveData.extraPauseSeconds === undefined) this._saveData.extraPauseSeconds = 0;
      } else {
        this._saveData = getDefaultData();
      }
    } catch (e) {
      this._saveData = getDefaultData();
    }
    // Load global balance
    try {
      const bal = my.getStorageSync({ key: 'global_balance' });
      if (bal && typeof bal.data === 'number') {
        this._saveData.balance = bal.data;
      }
    } catch (e) {}
  },

  saveData() {
    const data = {
      stage: this._saveData.stage,
      growthSeconds: this._saveData.growthSeconds,
      bankedTP: this._saveData.bankedTP,
      lastUpdate: Date.now(),
      lastEventCheck: this._saveData.lastEventCheck,
      activeEvent: this._saveData.activeEvent,
      totalGenerated: this._saveData.totalGenerated,
      eventsAttended: this._saveData.eventsAttended,
      eventTimer: this._saveData.eventTimer,
      extraPauseSeconds: this._saveData.extraPauseSeconds
    };
    try {
      my.setStorageSync({ key: 'goldentree_data', data: data });
    } catch (e) {}
  },

  // ── Offline Calculation ──
  processOfflineTime() {
    const now = Date.now();
    const elapsed = Math.floor((now - this._saveData.lastUpdate) / 1000);
    if (elapsed <= 0) return;

    // Active event: advance timer offline, apply penalty if expired
    if (this._saveData.activeEvent) {
      const timerLeft = (this._saveData.eventTimer || 0) - elapsed;
      if (timerLeft <= 0) {
        this.applyEventPenalty();
      } else {
        this._saveData.eventTimer = timerLeft;
      }
      this._saveData.lastUpdate = now;
      return;
    }

    // Extra pause from leaves penalty
    if (this._saveData.extraPauseSeconds > 0) {
      this._saveData.extraPauseSeconds = Math.max(0, this._saveData.extraPauseSeconds - elapsed);
      this._saveData.lastUpdate = now;
      return;
    }

    // Calculate TP generated while away
    const stage = getStageForSeconds(this._saveData.growthSeconds);
    const rate = STAGES[stage].rate;
    const cycles = Math.floor(elapsed / GENERATION_INTERVAL);
    const generated = cycles * rate;

    this._saveData.growthSeconds += elapsed;
    this._saveData.bankedTP += generated;
    this._saveData.totalGenerated += generated;
    this._saveData.stage = getStageForSeconds(this._saveData.growthSeconds);
    this._saveData.lastUpdate = now;

    // Check if event should have triggered while away
    const timeSinceEventCheck = Math.floor((now - this._saveData.lastEventCheck) / 1000);
    if (timeSinceEventCheck >= EVENT_INTERVAL) {
      this.triggerRandomEvent();
      this._saveData.lastEventCheck = now;
    }
  },

  // ── Live Timer ──
  startTimer() {
    this.stopTimer();
    this._timer = setInterval(() => {
      this.tick();
    }, 1000);
  },

  stopTimer() {
    if (this._timer) {
      clearInterval(this._timer);
      this._timer = null;
    }
  },

  tick() {
    // Extra pause (after leaves penalty)
    if (this._saveData.extraPauseSeconds > 0) {
      this._saveData.extraPauseSeconds -= 1;
      this.updateUI();
      return;
    }

    // Active event countdown
    if (this._saveData.activeEvent) {
      this._saveData.eventTimer = Math.max(0, (this._saveData.eventTimer || 0) - 1);
      if (this._saveData.eventTimer <= 0) {
        this.applyEventPenalty();
        return;
      }
      this.updateUI();
      return;
    }

    this._saveData.growthSeconds += 1;

    // Generate TP every 30 seconds
    if (this._saveData.growthSeconds % GENERATION_INTERVAL === 0) {
      const stage = getStageForSeconds(this._saveData.growthSeconds);
      const rate = STAGES[stage].rate;
      this._saveData.bankedTP += rate;
      this._saveData.totalGenerated += rate;
    }

    // Check stage advancement
    const newStage = getStageForSeconds(this._saveData.growthSeconds);
    if (newStage !== this._saveData.stage) {
      this._saveData.stage = newStage;
    }

    // Check event trigger
    const now = Date.now();
    const timeSinceEventCheck = Math.floor((now - this._saveData.lastEventCheck) / 1000);
    if (timeSinceEventCheck >= EVENT_INTERVAL) {
      if (Math.random() < 0.3) {
        this.triggerRandomEvent();
      }
      this._saveData.lastEventCheck = now;
    }

    // Auto-save every 30 seconds
    if (this._saveData.growthSeconds % 30 === 0) {
      this.saveData();
    }

    this.updateUI();
  },

  // ── Events ──
  triggerRandomEvent() {
    const event = EVENTS[Math.floor(Math.random() * EVENTS.length)];
    this._saveData.activeEvent = {
      ...event,
      triggeredAt: Date.now()
    };
    this._saveData.eventTimer = EVENT_TIMEOUT;
  },

  applyEventPenalty() {
    const event = this._saveData.activeEvent;
    if (!event) return;

    switch (event.id) {
      case 'drought':
        // Lose 20% of banked TP
        this._saveData.bankedTP = Math.floor(this._saveData.bankedTP * 0.80);
        break;
      case 'plague':
        // Drop 1 stage (regress growthSeconds to previous stage threshold)
        if (this._saveData.stage > 0) {
          const newStage = this._saveData.stage - 1;
          this._saveData.growthSeconds = STAGES[newStage].threshold;
          this._saveData.stage = newStage;
        }
        break;
      case 'storm':
        // Lose all banked TP
        this._saveData.bankedTP = 0;
        break;
      case 'leaves':
        // Pause generation for 5 extra minutes (300s)
        this._saveData.extraPauseSeconds = 300;
        break;
    }

    this._saveData.activeEvent = null;
    this._saveData.eventTimer = 0;
    this._saveData.lastEventCheck = Date.now();
    this.saveData();
    this.updateUI();
  },

  atenderEvento() {
    if (!this._saveData.activeEvent) return;
    this._saveData.activeEvent = null;
    this._saveData.eventTimer = 0;
    this._saveData.eventsAttended += 1;
    this._saveData.lastEventCheck = Date.now();
    this.saveData();
    this.updateUI();
  },

  // ── Actions ──
  cobrar() {
    if (this._saveData.bankedTP <= 0) return;
    const gross = Math.floor(this._saveData.bankedTP);
    const commission = Math.floor(gross * 0.15);
    const net = gross - commission;
    // Opción B: reset bankedTP only, keep stage & growthSeconds
    this._saveData.bankedTP = 0;
    const newBalance = (this.data.balance || 500) + net;
    this.setData({ balance: newBalance });
    try {
      my.setStorageSync({ key: 'global_balance', data: newBalance });
    } catch (e) {}
    this.saveData();
    this.updateUI();
  },

  regar() {
    const cost = 20;
    if (this.data.balance < cost) return;
    if (this._saveData.activeEvent) return;

    const newBalance = this.data.balance - cost;
    this._saveData.growthSeconds += 600; // Advance 10 minutes
    this._saveData.stage = getStageForSeconds(this._saveData.growthSeconds);

    this.setData({ balance: newBalance });
    try {
      my.setStorageSync({ key: 'global_balance', data: newBalance });
    } catch (e) {}
    this.saveData();
    this.updateUI();
  },

  // ── UI Update ──
  updateUI() {
    const s = this._saveData;
    const stage = s.stage;
    const currentThreshold = STAGES[stage].threshold;
    const isMax = stage >= STAGES.length - 1;
    const nextThreshold = isMax ? currentThreshold : STAGES[stage + 1].threshold;
    const nextName = isMax ? 'MAX' : STAGES[stage + 1].name;

    // Progress to next stage
    let progressPercent = 100;
    let timeToNext = '';
    if (!isMax) {
      const range = nextThreshold - currentThreshold;
      const progress = s.growthSeconds - currentThreshold;
      progressPercent = Math.min(Math.floor((progress / range) * 100), 100);
      const remaining = nextThreshold - s.growthSeconds;
      if (remaining > 3600) {
        timeToNext = Math.floor(remaining / 3600) + 'h ' + Math.floor((remaining % 3600) / 60) + 'min';
      } else if (remaining > 60) {
        timeToNext = Math.floor(remaining / 60) + 'min ' + (remaining % 60) + 's';
      } else {
        timeToNext = remaining + 's';
      }
    }

    // Commission calculation
    const gross = Math.floor(s.bankedTP);
    const commissionTP = Math.floor(gross * 0.15);
    const netTP = gross - commissionTP;

    const isGenerating = !s.activeEvent && !(s.extraPauseSeconds > 0);

    this.setData({
      stage: stage,
      stageName: STAGES[stage].name,
      stageRate: STAGES[stage].rate,
      growthSeconds: s.growthSeconds,
      bankedTP: gross,
      totalGenerated: Math.floor(s.totalGenerated),
      eventsAttended: s.eventsAttended,
      activeEvent: s.activeEvent,
      progressPercent: progressPercent,
      nextStageName: nextName,
      timeToNext: timeToNext,
      isMaxStage: isMax,
      canCobrar: gross >= 1,
      canRegar: this.data.balance >= 20 && !s.activeEvent,
      generating: isGenerating,
      commissionTP: commissionTP,
      netTP: netTP,
      eventTimer: s.eventTimer || 0,
      extraPauseSeconds: s.extraPauseSeconds || 0
    });
  }
});
