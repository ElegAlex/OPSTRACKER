import { Controller } from '@hotwired/stimulus';

/**
 * Controller Stimulus pour la modale de saisie de duree d'intervention.
 * Permet la saisie en heures:minutes avec validation Enter.
 */
export default class extends Controller {
    static targets = ['modal', 'heures', 'minutes', 'form'];
    static values = {
        operationId: Number,
        currentMinutes: Number
    };

    connect() {
        if (this.currentMinutesValue > 0) {
            const h = Math.floor(this.currentMinutesValue / 60);
            const m = this.currentMinutesValue % 60;
            this.heuresTarget.value = h;
            this.minutesTarget.value = m;
        }
    }

    open(event) {
        event.preventDefault();
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('flex');
        this.heuresTarget.focus();
        this.heuresTarget.select();
    }

    close() {
        this.modalTarget.classList.add('hidden');
        this.modalTarget.classList.remove('flex');
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    submitOnEnter(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.submit();
        }
    }

    submit() {
        const heures = parseInt(this.heuresTarget.value) || 0;
        const minutes = parseInt(this.minutesTarget.value) || 0;
        const totalMinutes = (heures * 60) + minutes;

        const hiddenInput = this.formTarget.querySelector('input[name="duree_minutes"]');
        if (hiddenInput) {
            hiddenInput.value = totalMinutes;
        }

        this.close();
        this.formTarget.submit();
    }

    validateMinutes(event) {
        let val = parseInt(event.target.value) || 0;
        if (val > 59) val = 59;
        if (val < 0) val = 0;
        event.target.value = val;
    }

    validateHeures(event) {
        let val = parseInt(event.target.value) || 0;
        if (val < 0) val = 0;
        event.target.value = val;
    }

    closeOnOverlay(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }
}
