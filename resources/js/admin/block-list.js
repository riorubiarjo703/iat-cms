/**
 * Reordering for the page-builder canvas.
 *
 * Blocks are a flat list — every registered block is a full-width page section
 * — so this needs one Sortable, not the nested group the menu tree uses. It
 * sends the whole order in one call; the server rejects any payload that does
 * not account for every block it already has, so a partial list cannot delete
 * anything.
 */
export default function blockList() {
    return {
        sortable: null,

        init() {
            this.build()

            Livewire.hook('morph.updated', ({ component }) => {
                if (component.id === this.$wire.id) {
                    this.$nextTick(() => this.build())
                }
            })
        },

        build() {
            this.sortable?.destroy()

            const list = this.$el.querySelector('[data-block-list]')
            if (!list) return

            this.sortable = window.Sortable.create(list, {
                handle: '[data-block-handle]',
                draggable: '[data-block]',
                animation: 150,
                ghostClass: 'scbd-block-ghost',
                onEnd: () => {
                    const order = [...list.querySelectorAll(':scope > [data-block]')]
                        .map((node) => node.dataset.block)

                    this.$wire.saveOrder(order)
                },
            })
        },
    }
}
