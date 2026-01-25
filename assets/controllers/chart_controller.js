import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

/**
 * Stimulus Controller pour Chart.js compatible Turbo Drive
 *
 * Usage :
 * <canvas data-controller="chart"
 *         data-chart-type-value="line"
 *         data-chart-data-value='{{ data|json_encode|e('html_attr') }}'
 *         data-chart-options-value='{{ options|json_encode|e('html_attr') }}'>
 * </canvas>
 */
export default class extends Controller {
    static values = {
        type: { type: String, default: 'line' },
        data: Object,
        options: { type: Object, default: {} }
    }

    connect() {
        // Detruire instance existante si presente (cas re-render Turbo)
        if (this.chart) {
            this.chart.destroy()
        }

        // Options par defaut pour un rendu propre
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 400
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 16,
                        font: { size: 11 }
                    }
                }
            }
        }

        // Fusionner avec options custom
        const mergedOptions = this.deepMerge(defaultOptions, this.optionsValue)

        // Creer le graphique
        this.chart = new Chart(this.element, {
            type: this.typeValue,
            data: this.dataValue,
            options: mergedOptions
        })
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
            this.chart = null
        }
    }

    /**
     * Recharger les donnees (utile pour refresh AJAX)
     */
    refresh(event) {
        if (event.detail && event.detail.data) {
            this.dataValue = event.detail.data
            this.chart.data = this.dataValue
            this.chart.update('active')
        }
    }

    /**
     * Deep merge d'objets
     */
    deepMerge(target, source) {
        const output = Object.assign({}, target)
        if (this.isObject(target) && this.isObject(source)) {
            Object.keys(source).forEach(key => {
                if (this.isObject(source[key])) {
                    if (!(key in target)) {
                        Object.assign(output, { [key]: source[key] })
                    } else {
                        output[key] = this.deepMerge(target[key], source[key])
                    }
                } else {
                    Object.assign(output, { [key]: source[key] })
                }
            })
        }
        return output
    }

    isObject(item) {
        return (item && typeof item === 'object' && !Array.isArray(item))
    }
}
