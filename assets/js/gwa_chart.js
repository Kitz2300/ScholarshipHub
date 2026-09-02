document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('gwaProgressChart');
    if (!canvas) return;

    const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
    const gwaData = JSON.parse(canvas.getAttribute('data-gwa') || '[]');
    const gwaReq = parseFloat(canvas.getAttribute('data-req') || '0');

    if (labels.length === 0 || gwaData.length === 0) return;

    // Line requirement dataset
    const reqData = labels.map(() => gwaReq);

    const datasets = [
        {
            label: 'Scholar GWA',
            data: gwaData,
            borderColor: '#2a6b37',
            backgroundColor: 'rgba(42, 107, 55, 0.12)',
            borderWidth: 3,
            pointBackgroundColor: '#1e4628',
            pointRadius: 6,
            pointHoverRadius: 8,
            tension: 0.2,
            fill: true
        }
    ];

    if (gwaReq > 0) {
        datasets.push({
            label: 'Min Requirement (' + gwaReq.toFixed(2) + ')',
            data: reqData,
            borderColor: '#dc3545',
            borderWidth: 2,
            borderDash: [6, 6],
            pointRadius: 0,
            fill: false
        });
    }

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    reverse: true, // 1.00 is top, higher numerical grades are lower
                    min: 1.00,
                    max: 3.50,
                    title: {
                        display: true,
                        text: 'GWA (1.00 is Highest)'
                    },
                    ticks: {
                        stepSize: 0.25,
                        callback: function(value) {
                            return value.toFixed(2);
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Academic Term'
                    }
                }
            }
        }
    });
});