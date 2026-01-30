@if(isset($tenant))
<div class="widget widget-tenant-info widget-{{ $size ?? 'medium' }}">
    <div class="details-section">
        <h3>Informations de l'organisation</h3>
        
        <div class="detail-row">
            <div class="detail-label">Nom</div>
            <div class="detail-value">{{ $tenant->name ?? 'N/A' }}</div>
        </div>

        @if($tenant->address)
        <div class="detail-row">
            <div class="detail-label">Adresse</div>
            <div class="detail-value">{{ $tenant->address }}</div>
        </div>
        @endif

        @if($tenant->phone)
        <div class="detail-row">
            <div class="detail-label">Téléphone</div>
            <div class="detail-value">{{ $tenant->phone }}</div>
        </div>
        @endif

        @if($tenant->email)
        <div class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value">{{ $tenant->email }}</div>
        </div>
        @endif

        @if($tenant->plan)
        <div class="detail-row">
            <div class="detail-label">Plan</div>
            <div class="detail-value">{{ $tenant->plan }}</div>
        </div>
        @endif
    </div>
</div>
@endif

