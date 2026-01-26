import { Controller } from '@hotwired/stimulus';

/**
 * Controller Stimulus pour la recherche de campagnes.
 * Filtre les cartes de campagnes en temps réel côté client.
 *
 * Usage:
 * <div data-controller="campaign-search">
 *   <input data-campaign-search-target="input" data-action="input->campaign-search#filter">
 *   <section data-campaign-search-target="section">
 *     <div data-campaign-search-target="card" data-name="Nom campagne">...</div>
 *   </section>
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'card', 'section', 'counter', 'noResults'];

    connect() {
        // Stocker l'état initial des compteurs
        this.originalCounts = {};
        this.counterTargets.forEach(counter => {
            const sectionId = counter.dataset.section;
            this.originalCounts[sectionId] = parseInt(counter.textContent, 10);
        });
    }

    filter() {
        const query = this.inputTarget.value.toLowerCase().trim();

        // Si recherche vide, tout afficher
        if (query === '') {
            this.showAll();
            return;
        }

        // Filtrer les cartes
        let totalVisible = 0;
        const visiblePerSection = {};

        this.cardTargets.forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const type = (card.dataset.type || '').toLowerCase();
            const matches = name.includes(query) || type.includes(query);

            card.style.display = matches ? '' : 'none';

            if (matches) {
                totalVisible++;
                const sectionId = card.closest('[data-campaign-search-target="section"]')?.dataset.sectionId;
                if (sectionId) {
                    visiblePerSection[sectionId] = (visiblePerSection[sectionId] || 0) + 1;
                }
            }
        });

        // Mettre à jour les compteurs et visibilité des sections
        this.sectionTargets.forEach(section => {
            const sectionId = section.dataset.sectionId;
            const count = visiblePerSection[sectionId] || 0;

            // Afficher/cacher la section
            section.style.display = count > 0 ? '' : 'none';

            // Mettre à jour le compteur
            const counter = this.counterTargets.find(c => c.dataset.section === sectionId);
            if (counter) {
                counter.textContent = count;
            }
        });

        // Afficher message "aucun résultat" si nécessaire
        if (this.hasNoResultsTarget) {
            this.noResultsTarget.style.display = totalVisible === 0 ? '' : 'none';
        }
    }

    showAll() {
        // Afficher toutes les cartes
        this.cardTargets.forEach(card => {
            card.style.display = '';
        });

        // Afficher toutes les sections et restaurer les compteurs
        this.sectionTargets.forEach(section => {
            section.style.display = '';
        });

        this.counterTargets.forEach(counter => {
            const sectionId = counter.dataset.section;
            if (this.originalCounts[sectionId] !== undefined) {
                counter.textContent = this.originalCounts[sectionId];
            }
        });

        // Cacher le message "aucun résultat"
        if (this.hasNoResultsTarget) {
            this.noResultsTarget.style.display = 'none';
        }
    }

    // Permettre de vider la recherche avec Escape
    clearOnEscape(event) {
        if (event.key === 'Escape') {
            this.inputTarget.value = '';
            this.showAll();
            this.inputTarget.blur();
        }
    }
}
