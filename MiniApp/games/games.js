Page({
  data: {
    balance: 500,
    gamesPlayed: 12,
    totalWon: 2500,
    streak: 3,
    activeTab: 'games'
  },
  onLoad(query) {
    console.info(`Page onLoad with query: ${JSON.stringify(query)}`);
  },
  goToGame(e) {
    const game = e.currentTarget.dataset.game;
    my.navigateTo({ url: `/pages/${game}/${game}` });
  },
  navigateTo(e) {
    const page = e.currentTarget.dataset.page;
    my.navigateTo({ url: `/pages/${page}/${page}` });
  }
});