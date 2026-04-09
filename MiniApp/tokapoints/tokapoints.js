Page({
  data: {
    balance: 500,
    activeTab: 'tokapoints',
    selectedPackage: null
  },
  onLoad(query) {
    console.info(`Page onLoad with query: ${JSON.stringify(query)}`);
  },
  selectPackage(e) {
    const price = parseInt(e.currentTarget.dataset.price);
    const cost = parseFloat(e.currentTarget.dataset.cost);
    my.alert({
      title: 'Comprar TokaCoins',
      content: `¿Confirmar compra de ${price} TC por $${cost}?`,
      buttonText: 'Confirmar',
      success: () => {
        this.setData({ balance: this.data.balance + price });
        my.showToast({ content: `¡${price} TC agregados!`, type: 'success' });
      }
    });
  },
  navigateTo(e) {
    const page = e.currentTarget.dataset.page;
    my.navigateTo({ url: `/pages/${page}/${page}` });
  }
});