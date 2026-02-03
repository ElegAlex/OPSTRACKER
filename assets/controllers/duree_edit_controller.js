import { Controller } from '@hotwired/stimulus';

/**
 * Controller Stimulus pour l'edition inline de duree d'intervention.
 * Utilisé dans la section "Terminé" de la vue terrain.
 */
export default class extends Controller {
    static targets = ['display', 'form', 'heures', 'minutes', 'total'];
    static values = { minutes: Number };

    edit(event) {
        event.preventDefault();
        this.displayTarget.classList.add('hidden');
        this.formTarget.classList.remove('hidden');
        this.heuresTarget.focus();
        this.heuresTarget.select();
    }

    cancel(event) {
        event.preventDefault();
        this.formTarget.classList.add('hidden');
        this.displayTarget.classList.remove('hidden');

        // Reset values
        const h = Math.floor(this.minutesValue / 60);
        const m = this.minutesValue % 60;
        this.heuresTarget.value = h;
        this.minutesTarget.value = m;
    }

    updateTotal() {
        const heures = parseInt(this.heuresTarget.value) || 0;
        const minutes = parseInt(this.minutesTarget.value) || 0;
        const total = (heures * 60) + minutes;
        this.totalTarget.value = total;
    }
}
