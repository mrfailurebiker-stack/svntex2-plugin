// wallet.js - Chart and interactivity
function renderChart(ctx, data, label) {
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: label,
            datasets: [{
                data: data,
                backgroundColor: ['#0077cc', '#228B22', '#ff9800', '#d32f2f'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    if (window.walletChartData) {
        renderChart(document.getElementById('walletChart').getContext('2d'), walletChartData.data, walletChartData.label);
    }
});
