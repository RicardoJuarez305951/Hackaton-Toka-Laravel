const BOARD = {
  width: 650,
  height: 650,
  slots: 7,
  centerX: 325,
  startY: 30,
  rowStartY: 100,
  rowSpacing: 80,
  pegSpacingX: 45,
  slotsY: 565,
  totalRows: 6,
};

function resolveApiBase() {
  try {
    const extConfig = (typeof my !== 'undefined' && my.getExtConfigSync)
      ? (my.getExtConfigSync() || {})
      : {};
    const configured = typeof extConfig.apiBase === 'string' ? extConfig.apiBase.trim() : '';

    if (configured) {
      return configured.replace(/\/+$/, '');
    }
  } catch (error) {
    console.warn('Unable to read Miniapp ext config apiBase:', error);
  }

  return 'http://localhost:8000/api';
}

const API_BASE = resolveApiBase();

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
    pegs: buildPegs(),
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
      },
    });
  },

  goBack() {
    my.navigateBack();
  },

  _clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  },

  _getSourceBoard(spline) {
    const source = (spline && spline.board) || {};
    const slots = Math.max(2, Number(source.slots) || BOARD.slots);

    return {
      width: Number(source.width) || BOARD.width,
      height: Number(source.height) || BOARD.height,
      rows: Math.max(1, Number(source.rows) || BOARD.totalRows),
      slots,
      startX: Number(source.start_x) || BOARD.centerX,
      startY: Number(source.start_y) || BOARD.startY,
      rowStartY: Number(source.row_start_y) || BOARD.rowStartY,
      rowSpacing: Number(source.row_spacing) || BOARD.rowSpacing,
      slotY: Number(source.slot_y) || BOARD.slotsY,
    };
  },

  _toViewPoint(spline, sourceX, sourceY) {
    const board = this._getSourceBoard(spline);

    return {
      x: (sourceX / board.width) * BOARD.width,
      y: (sourceY / board.height) * BOARD.height,
    };
  },

  _coerceSlotIndex(slotIndex, spline) {
    const board = this._getSourceBoard(spline);
    const raw = Number(slotIndex);
    const fallback = Math.floor(board.slots / 2);
    const safe = Number.isFinite(raw) ? Math.round(raw) : fallback;

    return this._clamp(safe, 0, board.slots - 1);
  },

  _getSlotCenterX(slotIndex, spline) {
    const board = this._getSourceBoard(spline);
    const safeSlot = this._coerceSlotIndex(slotIndex, spline);
    const slotWidth = board.width / board.slots;
    const sourceX = (safeSlot * slotWidth) + (slotWidth / 2);

    return (sourceX / board.width) * BOARD.width;
  },

  _slotIndexToPegCol(slotIndex, rowIndex, spline) {
    if (rowIndex <= 0) {
      return 0;
    }

    const board = this._getSourceBoard(spline);
    const safeSlot = this._coerceSlotIndex(slotIndex, spline);
    const normalized = safeSlot / (board.slots - 1);

    return this._clamp(Math.round(normalized * rowIndex), 0, rowIndex);
  },

  _getStartPoint(spline) {
    const board = this._getSourceBoard(spline);
    return this._toViewPoint(spline, board.startX, board.startY);
  },

  _getSlotY(spline) {
    const board = this._getSourceBoard(spline);
    return this._toViewPoint(spline, 0, board.slotY).y;
  },

  _triggerPegHit(row, col) {
    if (row < 0 || row >= this.data.pegs.length) return;
    if (col < 0 || col >= this.data.pegs[row].length) return;

    const pegs = this.data.pegs;
    pegs[row][col] = { hit: true };
    this.setData({ pegs, ballBouncing: true });

    setTimeout(() => {
      const pegs2 = this.data.pegs;
      pegs2[row][col] = { hit: false };
      this.setData({ pegs: pegs2, ballBouncing: false });
    }, 180);
  },

  _unlockPlay() {
    this.setData({ isPlaying: false, canPlay: true });
    my.hideLoading();
  },

  _finishRun(serverPrize) {
    const profit = serverPrize - this.data.bet;
    const newHistory = [{ profit }, ...this.data.history].slice(0, 5);

    this.setData({
      balance: this.data.balance,
      isPlaying: false,
      canPlay: true,
      showCoin: false,
      ballBouncing: false,
      history: newHistory,
      pegs: buildPegs(),
    });
  },

  playGame() {
    if (this.data.isPlaying || this.data.bet > this.data.balance) return;

    this.setData({ isPlaying: true, canPlay: false, showCoin: false, pegs: buildPegs() });
    my.showLoading({ content: 'Calculando...' });

    my.request({
      url: `${API_BASE}/plinko/play`,
      method: 'POST',
      data: {
        user_id: this.data.userId,
        bet: this.data.bet,
      },
      success: (res) => {
        my.hideLoading();

        if (!res.data.success) {
          this._unlockPlay();
          my.showToast({ content: res.data.message || 'Error', type: 'fail' });
          return;
        }

        const result = res.data;
        const startPoint = this._getStartPoint(result.spline || {});

        this.setData({
          balance: result.balance,
          coinTop: startPoint.y,
          coinLeft: startPoint.x,
          showCoin: true,
        });

        this.playSplineAnimation(result.spline || {}, result.slot_index, result.prize);
      },
      fail: (err) => {
        this._unlockPlay();
        my.showToast({ content: 'Error de conexion', type: 'fail' });
        console.error('API error:', err);
      },
    });
  },

  playSplineAnimation(spline, finalSlot, serverPrize) {
    const keyframes = Array.isArray(spline.keyframes) ? spline.keyframes : [];

    if (keyframes.length >= 2) {
      this.playKeyframesAnimation(spline, keyframes, serverPrize);
      return;
    }

    this.playLegacySplineAnimation(spline, finalSlot, serverPrize);
  },

  playLegacySplineAnimation(spline, finalSlot, serverPrize) {
    const positions = Array.isArray(spline.positions) ? spline.positions : [];
    const board = this._getSourceBoard(spline);
    const startPoint = this._getStartPoint(spline);
    let stepIndex = 0;

    this.setData({ coinTop: startPoint.y, coinLeft: startPoint.x, showCoin: true });

    const animateStep = () => {
      if (stepIndex >= positions.length) {
        const slotCol = this._coerceSlotIndex(finalSlot, spline);

        this.setData({
          coinTop: this._getSlotY(spline),
          coinLeft: this._getSlotCenterX(slotCol, spline),
        });

        setTimeout(() => this._finishRun(serverPrize), 380);
        return;
      }

      const hitCol = this._coerceSlotIndex(positions[stepIndex], spline);
      if (stepIndex > 0) {
        const rowIndex = stepIndex - 1;
        this._triggerPegHit(rowIndex, this._slotIndexToPegCol(hitCol, rowIndex, spline));
      }

      const sourceRowY = board.rowStartY + (stepIndex * board.rowSpacing);
      const rowPoint = this._toViewPoint(spline, 0, sourceRowY);
      this.setData({
        coinTop: rowPoint.y,
        coinLeft: this._getSlotCenterX(hitCol, spline),
      });

      stepIndex++;
      setTimeout(animateStep, 360);
    };

    setTimeout(animateStep, 320);
  },

  _normalizeFrames(spline, keyframes) {
    const sourceBoard = this._getSourceBoard(spline);

    return keyframes
      .map((frame) => {
        const tMs = Number(frame.t_ms);
        const x = Number(frame.x);
        const y = Number(frame.y);

        if (!Number.isFinite(tMs) || !Number.isFinite(x) || !Number.isFinite(y)) {
          return null;
        }

        return {
          t_ms: Math.max(0, tMs),
          x: (x / sourceBoard.width) * BOARD.width,
          y: (y / sourceBoard.height) * BOARD.height,
          event: frame.event || 'move',
          row: Number(frame.row),
          col: Number(frame.col),
        };
      })
      .filter((frame) => frame)
      .sort((a, b) => a.t_ms - b.t_ms);
  },

  playKeyframesAnimation(spline, keyframes, serverPrize) {
    const frames = this._normalizeFrames(spline, keyframes);

    if (frames.length < 2) {
      this.playLegacySplineAnimation(spline, spline.final_slot, serverPrize);
      return;
    }

    const durationMs = Math.max(Number(spline.duration_ms) || 0, frames[frames.length - 1].t_ms);
    const startedAt = Date.now();
    let hitCursor = 0;
    let segmentIndex = 0;

    const interpolate = (elapsedMs) => {
      while (segmentIndex < frames.length - 2 && elapsedMs > frames[segmentIndex + 1].t_ms) {
        segmentIndex++;
      }

      const from = frames[segmentIndex];
      const to = frames[Math.min(segmentIndex + 1, frames.length - 1)];

      if (to.t_ms <= from.t_ms) {
        return { x: to.x, y: to.y };
      }

      const ratio = (elapsedMs - from.t_ms) / (to.t_ms - from.t_ms);
      const clamped = Math.max(0, Math.min(1, ratio));

      return {
        x: from.x + ((to.x - from.x) * clamped),
        y: from.y + ((to.y - from.y) * clamped),
      };
    };

    const tick = () => {
      const elapsedMs = Math.min(Date.now() - startedAt, durationMs);

      while (hitCursor < frames.length && frames[hitCursor].t_ms <= elapsedMs) {
        const frame = frames[hitCursor];
        if (frame.event === 'hit' && Number.isFinite(frame.row) && Number.isFinite(frame.col)) {
          this._triggerPegHit(Math.round(frame.row), Math.round(frame.col));
        }
        hitCursor++;
      }

      const point = interpolate(elapsedMs);
      this.setData({ coinTop: point.y, coinLeft: point.x, showCoin: true });

      if (elapsedMs >= durationMs) {
        setTimeout(() => this._finishRun(serverPrize), 380);
        return;
      }

      setTimeout(tick, 16);
    };

    tick();
  },
});
