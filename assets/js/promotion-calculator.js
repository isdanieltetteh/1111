// Promotion Calculator for Submit Site Form
class PromotionCalculator {
    constructor() {
        this.promotionPrices = window.promotionPrices || {};
        this.featurePrices = window.featurePrices || {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateTotal();
    }

    bindEvents() {
        // Promotion type change
        document.querySelectorAll('input[name="promotion_type"]').forEach(input => {
            input.addEventListener('change', () => {
                this.toggleDurationSelection();
                this.updateDurationPrices();
                this.updateTotal();
            });
        });

        // Duration change
        document.addEventListener('change', (e) => {
            if (e.target.name === 'promotion_duration') {
                this.updateTotal();
            }
        });

        // Feature checkboxes
        document.querySelectorAll('input[name="features[]"]').forEach(input => {
            input.addEventListener('change', () => {
                this.updateTotal();
                this.updateBacklinkRequirement();
            });
        });
    }

    toggleDurationSelection() {
        const promotionType = document.querySelector('input[name="promotion_type"]:checked').value;
        const durationSection = document.getElementById('durationSelection');
        
        if (promotionType === 'none') {
            durationSection.style.display = 'none';
        } else {
            durationSection.style.display = 'block';
            this.updateDurationPrices();
        }
    }

    updateDurationPrices() {
        const promotionType = document.querySelector('input[name="promotion_type"]:checked').value;
        
        if (promotionType === 'none') return;
        
        const prices = this.promotionPrices[promotionType] || {};
        
        document.querySelectorAll('.duration-price').forEach(priceElement => {
            const duration = priceElement.closest('label').querySelector('input').value;
            const price = prices[duration] || 0;
            priceElement.textContent = '$' + parseFloat(price).toFixed(2);
        });
    }

    updateBacklinkRequirement() {
        const skipBacklink = document.querySelector('input[value="skip_backlink"]').checked;
        const backlinkField = document.getElementById('backlink_url');
        
        if (skipBacklink) {
            backlinkField.removeAttribute('required');
            backlinkField.placeholder = 'Backlink not required (paid feature)';
            backlinkField.style.opacity = '0.5';
        } else {
            backlinkField.setAttribute('required', 'required');
            backlinkField.placeholder = 'https://yoursite.com/page-with-backlink';
            backlinkField.style.opacity = '1';
        }
    }

    updateTotal() {
        let total = 0;

        // Add promotion cost
        const promotionType = document.querySelector('input[name="promotion_type"]:checked').value;
        const durationInput = document.querySelector('input[name="promotion_duration"]:checked');
        
        if (promotionType !== 'none' && durationInput) {
            const duration = durationInput.value;
            const promotionCost = this.promotionPrices[promotionType]?.[duration] || 0;
            total += parseFloat(promotionCost);
        }

        // Add feature costs
        document.querySelectorAll('input[name="features[]"]:checked').forEach(input => {
            const featureCost = this.featurePrices[input.value] || 0;
            total += parseFloat(featureCost);
        });

        // Update display
        const totalElement = document.getElementById('totalCost');
        if (totalElement) {
            totalElement.textContent = '$' + total.toFixed(2);
        }

        // Update submit button text
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            if (total > 0) {
                submitBtn.textContent = `Submit Site & Pay $${total.toFixed(2)}`;
                submitBtn.className = 'btn btn-warning w-full';
            } else {
                submitBtn.textContent = 'Submit Site for Review';
                submitBtn.className = 'btn btn-primary w-full';
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('totalCost')) {
        new PromotionCalculator();
    }
});
