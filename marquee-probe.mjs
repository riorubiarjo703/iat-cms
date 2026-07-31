import gsapPkg from 'gsap';
const gsap = gsapPkg.gsap ?? gsapPkg;

const loop = gsap.to({ x: 0 }, { x: -50, duration: 26, ease: 'none', repeat: -1 });
console.log('loop.timeScale() initial      :', loop.timeScale());

// CURRENT pattern: timeScale animated as a PROPERTY of a plain target
const el = {};
gsap.to(el, { timeScale: 2.5, duration: 0.3, overwrite: true });
console.log('target gained timeScale prop  :', 'timeScale' in el, '->', el.timeScale);
console.log('loop.timeScale() after buggy  :', loop.timeScale(), '<- boost NOT applied');

// CORRECT pattern: animate the TWEEN itself
gsap.to(loop, { timeScale: 2.5, duration: 0 });
console.log('loop.timeScale() after correct:', loop.timeScale(), '<- boost applied');
