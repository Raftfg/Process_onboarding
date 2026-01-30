@extends('layouts.dashboard')

@section('title', 'Rapports')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Rapports et Analyses</h1>
        <p style="color: #666;">Consultez les statistiques et analyses de votre système</p>
    </div>

    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3 class="card-title">Activité sur 30 jours</h3>
        </div>
        <div style="position: relative; height: 400px;">
            <canvas id="activityChart30"></canvas>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Répartition par type</h3>
            </div>
            <div style="position: relative; height: 300px;">
                <canvas id="typeChart"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Statistiques générales</h3>
            </div>
            <div id="general-stats" style="padding: 20px;">
                <div class="loading"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Graphique d'activité sur 30 jours
    axios.get('{{ route("dashboard.stats.chart") }}?period=month')
        .then(response => {
            const ctx = document.getElementById('activityChart30');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: response.data.labels,
                    datasets: [{
                        label: 'Activités',
                        data: response.data.data,
                        backgroundColor: 'rgba(102, 126, 234, 0.5)',
                        borderColor: '#667eea',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });

    // Charger les statistiques générales
    axios.get('{{ route("dashboard.stats") }}')
        .then(response => {
            const stats = response.data;
            document.getElementById('general-stats').innerHTML = `
                <div style="margin-bottom: 15px;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Utilisateurs totaux</div>
                    <div style="font-size: 24px; font-weight: 600;">${stats.total_users}</div>
                </div>
                <div style="margin-bottom: 15px;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Utilisateurs actifs</div>
                    <div style="font-size: 24px; font-weight: 600;">${stats.active_users}</div>
                </div>
                <div style="margin-bottom: 15px;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Activités aujourd'hui</div>
                    <div style="font-size: 24px; font-weight: 600;">${stats.today_activities}</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Notifications non lues</div>
                    <div style="font-size: 24px; font-weight: 600;">${stats.unread_notifications}</div>
                </div>
            `;
        });
</script>
@endpush
