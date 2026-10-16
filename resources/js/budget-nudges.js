export function budgetNudgeDeck() {
    return {
        index: 0,

        cards() {
            return [...this.$refs.deck.children];
        },

        cardStyle(cardIndex, tone) {
            const cards = this.cards();
            const distance = (cardIndex - this.index + cards.length) % cards.length;
            const depth = Math.min(distance, 2);
            const toneColor = tone === 'warning' ? 'warning' : 'primary';

            return {
                transform: `translate3d(${depth * 7}px, ${depth * 7}px, 0) rotate(${depth * 1.5}deg) scale(${1 - depth * 0.025})`,
                backgroundColor: `color-mix(in oklab, var(--color-${toneColor}) 16%, var(--color-base-100))`,
                opacity: distance > 2 ? '0' : String(1 - depth * 0.12),
                zIndex: String(distance === 0 ? 20 : 10 - distance),
                pointerEvents: distance === 0 ? 'auto' : 'none',
            };
        },

        move(direction) {
            const cards = this.cards();
            const nextIndex = Math.max(0, Math.min(cards.length - 1, this.index + direction));
            this.index = nextIndex;
        },
    };
}
