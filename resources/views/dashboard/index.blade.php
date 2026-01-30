@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600; margin-bottom: 10px;">
            Bienvenue, {{ Auth::user()->name ?? 'Administrateur' }} !
        </h1>
        <p style="color: #666; font-size: 16px;">
            {{ $tenantLayout['welcome_message'] ?? 'Voici un aperçu de votre espace MedKey' }}
        </p>
    </div>

    @php
        $widgets = $tenantLayout['dashboard_widgets'] ?? [
            'stats' => true,
            'activities' => true,
            'calendar' => true,
            'quick_actions' => true,
        ];
        $gridColumns = $tenantLayout['grid_columns'] ?? 3;
    @endphp

    @if($widgets['stats'] ?? true)
        @include('dashboard.partials.stats-cards')
    @endif

    @if(($widgets['activities'] ?? true) || ($widgets['calendar'] ?? true))
        <div style="display: grid; grid-template-columns: {{ ($widgets['activities'] ?? true) && ($widgets['calendar'] ?? true) ? '2fr 1fr' : '1fr' }}; gap: 20px; margin-bottom: 30px;" class="dashboard-grid">
            @if($widgets['activities'] ?? true)
                <div>
                    @include('dashboard.partials.recent-activity')
                </div>
            @endif
            @if($widgets['calendar'] ?? true)
                <div>
                    @include('dashboard.partials.calendar-widget')
                </div>
            @endif
        </div>
    @endif

    @if($widgets['quick_actions'] ?? true)
        @include('dashboard.partials.quick-actions')
    @endif

    @if(isset($onboarding))
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h3 class="card-title">Informations de l'hôpital</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Nom de l'hôpital</div>
                    <div style="font-size: 16px; font-weight: 600;">{{ $onboarding->hospital_name ?? 'N/A' }}</div>
                </div>
                
                @if($onboarding->hospital_address)
                <div>
                    <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Adresse</div>
                    <div style="font-size: 16px;">{{ $onboarding->hospital_address }}</div>
                </div>
                @endif
                
                @if($onboarding->hospital_phone)
                <div>
                    <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Téléphone</div>
                    <div style="font-size: 16px;">{{ $onboarding->hospital_phone }}</div>
                </div>
                @endif
                
                <div>
                    <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px;">Sous-domaine</div>
                    <div style="font-size: 16px; font-weight: 600; color: var(--primary-color);">{{ $onboarding->subdomain ?? 'N/A' }}</div>
                </div>
            </div>
        </div>
    @endif

    <!-- Graphique d'activité -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h3 class="card-title">Activité sur 7 jours</h3>
        </div>
        <div style="position: relative; height: 300px;">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Graphique d'activité
    const ctx = document.getElementById('activityChart');
    if (ctx) {
        axios.get('{{ route("dashboard.stats.chart") }}')
            .then(response => {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Activités',
                            data: response.data.data,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
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
            })
            .catch(error => {
                console.error('Erreur lors du chargement du graphique:', error);
            });
    }
</script>
@endpush
