import { Controller } from "@hotwired/stimulus"

/**
 * T-2205 : Stimulus controller pour les messages flash
 * - Auto-dismiss apres un timeout configurable
 * - Animation de disparition
 * - Fermeture manuelle via bouton
 */
export default class extends Controller {
    static values = { timeout: Number }

    connect() {
        if (this.timeoutValue > 0) {
            this.timeoutId = setTimeout(() => this.dismiss(), this.timeoutValue)
        }
        // Re-render Feather icons for dynamically loaded content
        if (typeof feather !== 'undefined') {
            feather.replace()
        }
    }

    disconnect() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId)
        }
    }

    dismiss() {
        this.element.classList.add('opacity-0', 'transition-opacity', 'duration-300')
        setTimeout(() => this.element.remove(), 300)
    }
}
