/**
 * Nested drag-and-drop for the menu builder.
 *
 * Filament bundles SortableJS and registers an `x-sortable` directive, but that
 * directive returns early when the index is unchanged — which is exactly what
 * happens when an item moves between two lists at the same position — and it
 * emits no event to persist from. So this drives `window.Sortable` directly.
 *
 * Every nesting level, plus the root drop zone, is a Sortable list in the same
 * group, which is what allows an item to be dragged into, out of, and between
 * parents. After any drop the whole tree is serialised and sent in one call:
 * a nested move can change many rows at once, and sending them individually
 * would leave the tree briefly inconsistent.
 */
export default function menuTree() {
    return {
        lists: [],
        root: null,

        init() {
            // $el inside an Alpine handler is the element the directive sits
            // on, not the component root — so a method called from a button's
            // x-on:click searched inside that button and silently found
            // nothing. This is why Expand and Collapse did not work while the
            // same methods worked when called directly. Capture the root once,
            // here, where $el is the component.
            this.root = this.$el

            this.buildSortables()
            this.restoreExpansion()

            // Livewire replaces the DOM after add/delete, so the lists have to
            // be rebound or dragging silently stops working on new rows.
            this.$wire.$hook?.('morph.updated', () => this.buildSortables())
            document.addEventListener('livewire:navigated', () => this.buildSortables())
            Livewire.hook('morph.updated', ({ component }) => {
                if (component.id === this.$wire.id) {
                    this.$nextTick(() => {
                        this.buildSortables()
                        // Expansion is a DOM class, so a morph wipes it. Without
                        // this the tree folds shut every time an item is added,
                        // edited or deleted.
                        this.restoreExpansion()
                    })
                }
            })
        },

        buildSortables() {
            this.lists.forEach((list) => list.destroy?.())
            this.lists = []

            const containers = [
                ...this.root.querySelectorAll('[data-menu-root], [data-menu-children]'),
            ]

            containers.forEach((container) => {
                this.lists.push(
                    window.Sortable.create(container, {
                        group: 'menu-tree',
                        handle: '[data-menu-handle]',
                        draggable: '[data-menu-item]',
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.6,
                        // Default is 5px, which makes dropping onto an empty
                        // child list a pixel-hunt. This is the difference
                        // between nesting being fiddly and being easy.
                        emptyInsertThreshold: 28,
                        ghostClass: 'scbd-tree-ghost',
                        // Collapsed and childless lists are hidden at rest; a
                        // drag has to reveal them or nothing can be nested.
                        onStart: () => this.root.classList.add('scbd-dragging'),
                        onEnd: () => {
                            this.root.classList.remove('scbd-dragging')
                            this.persist()
                        },
                    }),
                )
            })

            const rootDrop = this.root.querySelector('[data-menu-root-drop]')

            if (rootDrop) {
                this.lists.push(
                    window.Sortable.create(rootDrop, {
                        group: 'menu-tree',
                        draggable: '[data-menu-item]',
                        animation: 150,
                        onStart: () => this.root.classList.add('scbd-dragging'),
                        onEnd: () => this.root.classList.remove('scbd-dragging'),
                        onAdd: (event) => {
                            // The zone is a target, not a container: move the
                            // node to the real root list, then persist.
                            const root = this.root.querySelector('[data-menu-root]')
                            root.appendChild(event.item)
                            this.persist()
                        },
                    }),
                )
            }

            this.bindToggles()
        },

        bindToggles() {
            this.root.querySelectorAll('[data-menu-toggle]').forEach((button) => {
                if (button.dataset.bound) return
                button.dataset.bound = '1'

                button.addEventListener('click', () => {
                    const item = button.closest('[data-menu-item]')
                    item.classList.toggle('scbd-tree-item-open')
                    this.rememberExpansion()
                })
            })
        },

        /** Flattens the DOM back into (id, parent, sort) triples. */
        persist() {
            const nodes = []

            const walk = (list, parentId) => {
                ;[...list.children]
                    .filter((child) => child.matches('[data-menu-item]'))
                    .forEach((child, index) => {
                        nodes.push({
                            id: child.dataset.menuItem,
                            parent: parentId,
                            sort: index + 1,
                        })

                        const children = child.querySelector(':scope > [data-menu-children]')
                        if (children) walk(children, child.dataset.menuItem)
                    })
            }

            const root = this.root.querySelector('[data-menu-root]')
            if (root) walk(root, null)

            this.$wire.saveTree(nodes)
        },

        expandAll() {
            this.root.querySelectorAll('[data-menu-item]').forEach((item) => item.classList.add('scbd-tree-item-open'))
            this.rememberExpansion()
        },

        collapseAll() {
            this.root.querySelectorAll('[data-menu-item]').forEach((item) => item.classList.remove('scbd-tree-item-open'))
            this.rememberExpansion()
        },

        /**
         * Expansion is a view preference, not data — persisting it server-side
         * would mean a round trip to open a twisty. Kept per menu so two menus
         * do not share one state.
         */
        storageKey() {
            return `scbd-menu-expanded:${window.location.pathname}`
        },

        rememberExpansion() {
            const open = [...this.root.querySelectorAll('[data-menu-item].scbd-tree-item-open')]
                .map((item) => item.dataset.menuItem)

            localStorage.setItem(this.storageKey(), JSON.stringify(open))
        },

        restoreExpansion() {
            let open = []

            try {
                open = JSON.parse(localStorage.getItem(this.storageKey()) ?? '[]')
            } catch {
                open = []
            }

            // Default to expanded on first visit: a collapsed tree hides the
            // nesting the page exists to show.
            if (open.length === 0 && localStorage.getItem(this.storageKey()) === null) {
                this.expandAll()

                return
            }

            open.forEach((id) => {
                this.root.querySelector(`[data-menu-item="${id}"]`)?.classList.add('scbd-tree-item-open')
            })
        },
    }
}
