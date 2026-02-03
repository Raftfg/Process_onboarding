@extends('layouts.dashboard')

@section('title', 'Simulation Client Externe')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Formulaire de test -->
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Nouvelle Simulation</h3>
                </div>
                <form action="{{ route('module.test.trigger') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <div class="form-group">
                            <label>Application Cliente (Header X-App-Name)</label>
                            <input type="text" name="app_name" class="form-control" value="Secteur-Sante-v1" required>
                        </div>
                        <div class="form-group">
                            <label>Nom de l'Organisation</label>
                            <input type="text" name="organization_name" class="form-control" placeholder="Ex: Clinique du Lac" required>
                            <small class="text-muted">Unique par application.</small>
                        </div>
                        <div class="form-group">
                            <label>Email Admin</label>
                            <input type="email" name="email" class="form-control" placeholder="admin@clinique.com" required>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-block">
                            Lancer l'Onboarding via API
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des tenants simulés -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tenants Créés (Base Simulation Client)</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Organisation</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Database</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tenants as $tenant)
                                <tr>
                                    <td>{{ $tenant->organization_name }}</td>
                                    <td>{{ $tenant->admin_email }}</td>
                                    <td>
                                        @if($tenant->status === 'active')
                                            <span class="badge badge-success">Actif</span>
                                        @else
                                            <span class="badge badge-warning">En attente</span>
                                        @endif
                                    </td>
                                    <td><small><code>{{ $tenant->database_name }}</code></small></td>
                                    <td>
                                        @if($tenant->domain_url)
                                            <a href="{{ $tenant->domain_url }}" target="_blank" class="btn btn-xs btn-info">Visiter</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Aucune simulation enregistrée.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
