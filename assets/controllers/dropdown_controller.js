import { Controller } from '@hotwired/stimulus';

/**
 * Controller Stimulus pour les menus dropdown.
 * Se reconnecte automatiquement apres navigation Turbo (cache restore).
 *
 * Usage:
 * <div data-controller="dropdown">
 *   <button data-action="dropdown#toggle">...</button>
 *   <div data-dropdown-target="menu" class="dropdown-menu">...</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['menu'];

    connect() {
        // Bind pour pouvoir retirer le listener
        this.closeOnOutsideClick = this.closeOnOutsideClick.bind(this);
        document.addEventListener('click', this.closeOnOutsideClick);
    }

    disconnect() {
        document.removeEventListener('click', this.closeOnOutsideClick);
    }

    toggle(event) {
        event.stopPropagation();

        // Fermer tous les autres dropdowns ouverts
        document.querySelectorAll('.dropdown-menu.active').forEach((menu) => {
            if (menu !== this.menuTarget) {
                menu.classList.remove('active');
            }
        });

        // Toggle le menu courant
        this.menuTarget.classList.toggle('active');
    }

    close() {
        this.menuTarget.classList.remove('active');
    }

    closeOnOutsideClick(event) {
        // Si le clic est en dehors du controller, fermer le menu
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }
}
