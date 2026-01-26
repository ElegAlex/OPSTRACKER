import { Controller } from "@hotwired/stimulus"

/**
 * Stimulus Controller pour changer la granularite du graphique progression
 *
 * Usage :
 * <div data-controller="granularity" data-granularity-campagne-value="123">
 *     <button data-action="click->granularity#change" data-value="day">Jour</button>
 *     <button data-action="click->granularity#change" data-value="week">Semaine</button>
 *     <button data-action="click->granularity#change" data-value="month">Mois</button>
 * </div>
 */
export default class extends Controller {
    static values = {
        campagne: Number
    }

    change(event) {
        event.preventDefault()

        const granularity = event.currentTarget.dataset.value
        if (!granularity) {
            console.warn('No granularity value found on button')
            return
        }

        const frame = document.getElementById('progression-chart')
        if (!frame) {
            console.warn('Turbo Frame #progression-chart not found')
            return
        }

        // Construire l'URL avec la nouvelle granularite
        const url = `/dashboard/campagne/${this.campagneValue}/progression?granularity=${granularity}`

        // Recharger le Turbo Frame
        frame.src = url
    }
}
