import menuTree from './menu-tree'

// Filament boots Alpine itself, so components register on its init event
// rather than importing Alpine here — importing a second copy would leave two
// instances fighting over the same DOM.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('menuTree', menuTree)
})
