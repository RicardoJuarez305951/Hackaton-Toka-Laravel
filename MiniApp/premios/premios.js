Page({
  data: {
    balance: 500,
    activeTab: 'premios'
  },
  onLoad(query) {
    console.info(`Page onLoad with query: ${JSON.stringify(query)}`);
  },
  redeem(e) {
    const cost = parseInt(e.currentTarget.dataset.cost);
    if (this.data.balance < cost) {
      my.alert({
        title: 'Saldo insuficiente',
        content: `Necesitas ${cost} TP para canjear este premio`
      });
      return;
    }
    
    my.alert({
      title: 'Confirmar canje',
      content: `¿Canjeas ${cost} TP por este premio?`,
      buttonText: 'Confirmar',
      success: () => {
        this.setData({ balance: this.data.balance - cost });
        my.showToast({ content: '¡Premio canjeado!', type: 'success' });
      }
    });
  },
  navigateTo(e) {
    const page = e.currentTarget.dataset.page;
    my.navigateTo({ url: `/pages/${page}/${page}` });
  }
});