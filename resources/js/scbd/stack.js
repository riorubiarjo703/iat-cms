export function initCardStack(gsap) {
  const cards = Array.from(document.querySelectorAll('[data-card]'));

  cards.forEach((card, index) => {
    if (index === cards.length - 1) return;

    gsap.fromTo(card,
      { scale: 1, y: 0 },
      {
        scale: 0.96 - index * 0.012,
        y: -12,
        ease: 'none',
        scrollTrigger: {
          trigger: cards[index + 1],
          start: 'top bottom',
          end: 'top 110px',
          scrub: 0.4,
        },
      });
  });
}
