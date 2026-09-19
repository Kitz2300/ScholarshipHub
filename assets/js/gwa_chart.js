document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('gwaProgressChart');
    if (!canvas) return;

    let rawLabels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
    let rawGwaData = JSON.parse(canvas.getAttribute('data-gwa') || '[]');
    const gwaReq = parseFloat(canvas.getAttribute('data-req') || '0');

    if (rawLabels.length === 0 || rawGwaData.length === 0) return;

    // Helper to order semesters within the same school year (1st -> 2nd -> Summer)
    function getSemesterWeight(termStr) {
        if (termStr.includes('1st Semester')) return 1;
        if (termStr.includes('2nd Semester')) return 2;
        if (termStr.includes('Summer')) return 3;
        return 4;
    }

    // Combine labels and values to guarantee strict chronological sorting
    const combinedData = rawLabels.map((label, index) => {
        const parts = label.split(' (');
        const sy = parts[0] || '';
        const sem = parts[1] ? parts[1].replace(')', '') : '';
        return {
            label: label,
            gwa: rawGwaData[index],
            sy: sy,
            semWeight: getSemesterWeight(sem)
        };
    });

    combinedData.sort((a, b) => {
        if (a.sy !== b.sy) {
            return a.sy.localeCompare(b.sy);
        }
        return a.semWeight - b.semWeight;
    });

    const labels = combinedData.map(item => item.label);
    const gwaData = combinedData.map(item => item.gwa);

    // Dynamic Y-Axis Max calculation so failing grades (> 3.50) are not cut off
    const maxGWA = Math.max(3.50, ...gwaData, gwaReq);

    // Dynamic point colors: red point if GWA fails the minimum requirement
    const pointColors = gwaData.map(gwa => (gwaReq > 0 && gwa > gwaReq) ? '#dc3545' : '#1e4628');
    const pointHoverColors = gwaData.map(gwa => (gwaReq > 0 && gwa > gwaReq) ? '#bd2130' : '#2a6b37');

    // Line requirement dataset
    const reqData = labels.map(() => gwaReq);

    const datasets = [
        {
            label: 'Scholar GWA',
            data: gwaData,
            borderColor: '#2a6b37',
            backgroundColor: 'rgba(42, 107, 55, 0.12)',
            borderWidth: 3,
            pointBackgroundColor: pointColors,
            pointBorderColor: pointColors,
            pointHoverBackgroundColor: pointHoverColors,
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
                            let valStr = context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                            if (context.datasetIndex === 0 && gwaReq > 0) {
                                if (context.parsed.y > gwaReq) {
                                    valStr += ' (Failed Requirement)';
                                } else {
                                    valStr += ' (Passed Requirement)';
                                }
                            }
                            return valStr;
                        }
                    }
                }
            },
            scales: {
                y: {
                    reverse: true, // 1.00 is top (highest academic achievement)
                    min: 1.00,
                    max: maxGWA,
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