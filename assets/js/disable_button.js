document.addEventListener('DOMContentLoaded', () => {
    // Target all standard forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {
            // 1. If form is already submitting, prevent second submit
            if (this.dataset.submitting === 'true') {
                e.preventDefault();
                return false;
            }

            // 2. Mark form as submitting
            this.dataset.submitting = 'true';

            // 3. Disable submit buttons on the next tick so POST data is preserved
            const submitButtons = this.querySelectorAll('button[type="submit"], input[type="submit"]');
            
            setTimeout(() => {
                submitButtons.forEach(btn => {
                    btn.disabled = true;
                    // Optional visual feedback:
                    if (btn.tagName === 'BUTTON') {
                        btn.innerHTML = 'Processing...';
                    } else {
                        btn.value = 'Processing...';
                    }
                });
            }, 0);
        });
    });
});